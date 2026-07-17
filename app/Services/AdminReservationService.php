<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationOverlapException;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Telemetry\MetricsRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AdminReservationService
{
    public function __construct(
        private readonly MetricsRecorder $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Reservation
    {
        return $this->record('create', $attributes['room_id'], function () use ($attributes): Reservation {
            return DB::transaction(function () use ($attributes): Reservation {
                $room = $this->activeRoom($attributes['room_id']);

                $this->lockRoomDay($room, $attributes['date']);

                if ($this->hasOverlap($room, $attributes)) {
                    throw new ReservationOverlapException('Selected room is already reserved for this time range.');
                }

                $reservation = Reservation::query()
                    ->where('room_id', $room->id)
                    ->whereDate('reserved_date', $attributes['date'])
                    ->where('starts_at', $attributes['start_time'])
                    ->first();

                $reservation ??= new Reservation([
                    'room_id' => $room->id,
                    'reserved_date' => $attributes['date'],
                    'starts_at' => $attributes['start_time'],
                ]);

                $reservation->fill($this->reservationAttributes($attributes, $attributes['status'] ?? ReservationStatus::Confirmed));

                $this->saveOrConflict($reservation);

                return $reservation->load('room');
            });
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Reservation $reservation, array $attributes): Reservation
    {
        return $this->record('update', $attributes['room_id'], function () use ($reservation, $attributes): Reservation {
            return DB::transaction(function () use ($reservation, $attributes): Reservation {
                $room = $this->activeRoom($attributes['room_id']);

                $this->lockRoomDay($room, $attributes['date']);

                if ($this->hasOverlap($room, $attributes, $reservation)) {
                    throw new ReservationOverlapException('Selected room is already reserved for this time range.');
                }

                $reservation->fill([
                    'room_id' => $room->id,
                    ...$this->reservationAttributes($attributes, $attributes['status'] ?? $reservation->status),
                ]);

                $this->saveOrConflict($reservation);

                return $reservation->load('room');
            });
        });
    }

    public function cancel(Reservation $reservation): Reservation
    {
        $reservation->loadMissing('room');

        return $this->record('cancel', $reservation->room->slug, function () use ($reservation): Reservation {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
            ]);

            return $reservation->load('room');
        });
    }

    public function delete(Reservation $reservation): void
    {
        $reservation->loadMissing('room');
        $roomSlug = $reservation->room->slug;

        $this->record('delete', $roomSlug, function () use ($reservation): null {
            $reservation->delete();

            return null;
        });
    }

    /**
     * Lock the room/day reservation rows so concurrent writes serialize on the
     * overlap check instead of both passing it (TOCTOU race).
     */
    private function lockRoomDay(Room $room, string $date): void
    {
        Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $date)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Persist the reservation, translating a DB overlap/unique violation from a
     * concurrent request into a clean conflict instead of a 500.
     */
    private function saveOrConflict(Reservation $reservation): void
    {
        try {
            $reservation->save();
        } catch (QueryException $exception) {
            // 23P01 = exclusion_violation, 23505 = unique_violation.
            if (in_array($exception->getCode(), ['23P01', '23505'], true)) {
                throw new ReservationOverlapException('Selected room is already reserved for this time range.');
            }

            throw $exception;
        }
    }

    private function activeRoom(string $slug): Room
    {
        return Room::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hasOverlap(Room $room, array $attributes, ?Reservation $ignoreReservation = null): bool
    {
        return Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $attributes['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->when($ignoreReservation, fn ($query) => $query->whereKeyNot($ignoreReservation->id))
            ->where('starts_at', '<', $attributes['end_time'])
            ->where('ends_at', '>', $attributes['start_time'])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function reservationAttributes(array $attributes, ReservationStatus|string $status): array
    {
        return [
            'status' => $status,
            'reserved_date' => $attributes['date'],
            'starts_at' => $attributes['start_time'],
            'ends_at' => $attributes['end_time'],
            'first_name' => trim($attributes['first_name']),
            'last_name' => trim($attributes['last_name']),
            'email' => str($attributes['email'])->lower()->toString(),
            'phone' => trim($attributes['phone']),
            'notes' => isset($attributes['notes']) ? trim($attributes['notes']) : null,
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function record(string $operation, string $roomSlug, callable $callback): mixed
    {
        $startedAt = microtime(true);
        $status = 'success';

        try {
            return $callback();
        } catch (ReservationOverlapException $exception) {
            $status = 'conflict';

            throw $exception;
        } catch (\Throwable $exception) {
            $status = 'error';

            throw $exception;
        } finally {
            $labels = [
                'service' => 'admin_reservation',
                'operation' => $operation,
                'room_id' => $roomSlug,
                'status' => $status,
            ];

            $this->metrics->increment("booking_reservation_{$operation}_total", $labels);
            $this->metrics->observe(
                "booking_reservation_{$operation}_duration_seconds",
                microtime(true) - $startedAt,
                $labels,
            );
        }
    }
}
