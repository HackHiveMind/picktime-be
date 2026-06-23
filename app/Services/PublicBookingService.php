<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationOverlapException;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Telemetry\MetricsRecorder;

class PublicBookingService
{
    public function __construct(
        private readonly MetricsRecorder $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, string $endTime): Reservation
    {
        return $this->record('create', $attributes['room_id'], function () use ($attributes, $endTime): Reservation {
            $room = Room::query()
                ->where('slug', $attributes['room_id'])
                ->where('is_active', true)
                ->firstOrFail();

            $alreadyReserved = Reservation::query()
                ->where('room_id', $room->id)
                ->whereDate('reserved_date', $attributes['date'])
                ->where('status', '!=', ReservationStatus::Cancelled)
                ->where('starts_at', '<', $endTime)
                ->where('ends_at', '>', $attributes['start_time'])
                ->exists();

            if ($alreadyReserved) {
                throw new ReservationOverlapException('Selected room is already reserved for this time slot.');
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

            $reservation->fill([
                'status' => ReservationStatus::Confirmed,
                'ends_at' => $endTime,
                'first_name' => trim($attributes['first_name']),
                'last_name' => trim($attributes['last_name']),
                'email' => str($attributes['email'])->lower()->toString(),
                'phone' => trim($attributes['phone']),
                'notes' => isset($attributes['notes']) ? trim($attributes['notes']) : null,
            ])->save();

            return $reservation->load('room');
        });
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
                'service' => 'public_booking',
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
