<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'room_id' => ['nullable', 'string', 'exists:rooms,slug'],
        ]);

        $reservations = Reservation::query()
            ->with('room')
            ->when(
                $validated['date_from'] ?? null,
                fn ($query, string $date) => $query->whereDate('reserved_date', '>=', $date),
            )
            ->when(
                $validated['date_to'] ?? null,
                fn ($query, string $date) => $query->whereDate('reserved_date', '<=', $date),
            )
            ->when($validated['room_id'] ?? null, function ($query, string $roomId) {
                $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('slug', $roomId));
            })
            ->orderBy('reserved_date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Reservation $reservation): array => $this->reservationResponse($reservation))
            ->values();

        return new JsonResponse(['data' => $reservations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateReservationPayload($request);

        $room = $this->activeRoom($validated['room_id']);

        if ($this->hasOverlap($room, $validated)) {
            return new JsonResponse([
                'message' => 'Selected room is already reserved for this time range.',
            ], 422);
        }

        $reservation = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $validated['date'])
            ->where('starts_at', $validated['start_time'])
            ->first();

        $reservation ??= new Reservation([
            'room_id' => $room->id,
            'reserved_date' => $validated['date'],
            'starts_at' => $validated['start_time'],
        ]);

        $reservation->fill([
            'status' => $validated['status'] ?? ReservationStatus::Confirmed,
            'ends_at' => $validated['end_time'],
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => str($validated['email'])->lower()->toString(),
            'phone' => trim($validated['phone']),
            'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
        ])->save();

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation->load('room')),
        ], 201);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $validated = $this->validateReservationPayload($request);

        $room = $this->activeRoom($validated['room_id']);

        if ($this->hasOverlap($room, $validated, $reservation)) {
            return new JsonResponse([
                'message' => 'Selected room is already reserved for this time range.',
            ], 422);
        }

        $reservation->update([
            'room_id' => $room->id,
            'status' => $validated['status'] ?? $reservation->status,
            'reserved_date' => $validated['date'],
            'starts_at' => $validated['start_time'],
            'ends_at' => $validated['end_time'],
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => str($validated['email'])->lower()->toString(),
            'phone' => trim($validated['phone']),
            'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
        ]);

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation->load('room')),
        ]);
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $reservation->update([
            'status' => ReservationStatus::Cancelled,
        ]);

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation->load('room')),
        ]);
    }

    public function destroy(Reservation $reservation): Response
    {
        $reservation->delete();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateReservationPayload(Request $request): array
    {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'slug')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['end_time'] ??= CarbonImmutable::createFromFormat('H:i', $validated['start_time'])
            ->addHour()
            ->format('H:i');

        return $validated;
    }

    private function activeRoom(string $slug): Room
    {
        return Room::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function hasOverlap(Room $room, array $validated, ?Reservation $ignoreReservation = null): bool
    {
        return Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $validated['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->when($ignoreReservation, fn ($query) => $query->whereKeyNot($ignoreReservation->id))
            ->where('starts_at', '<', $validated['end_time'])
            ->where('ends_at', '>', $validated['start_time'])
            ->exists();
    }

    private function reservationResponse(Reservation $reservation): array
    {
        return [
            'id' => (string) $reservation->id,
            'room_id' => $reservation->room->slug,
            'room_name' => $reservation->room->name,
            'date' => $reservation->reserved_date->format('Y-m-d'),
            'start_time' => $this->formatTime($reservation->starts_at),
            'end_time' => $this->formatTime($reservation->ends_at),
            'first_name' => $reservation->first_name,
            'last_name' => $reservation->last_name,
            'email' => $reservation->email,
            'phone' => $reservation->phone,
            'status' => $reservation->status->value,
            'notes' => $reservation->notes ?? '',
            'created_at' => $reservation->created_at?->toISOString(),
        ];
    }

    private function formatTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
