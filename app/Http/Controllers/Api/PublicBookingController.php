<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\BookingSlotRules;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationOverlapException;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\PublicBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class PublicBookingController extends Controller
{
    public function rooms(): JsonResponse
    {
        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room): array => $this->roomResponse($room))
            ->values();

        return new JsonResponse(['data' => $rooms]);
    }

    public function availability(Request $request, Room $room, BookingSlotRules $slotRules): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reservations = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $validated['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->get(['starts_at', 'ends_at']);

        return new JsonResponse([
            'data' => $this->availabilityResponse($room, $validated['date'], $reservations, $slotRules),
        ]);
    }

    public function availabilityIndex(Request $request, BookingSlotRules $slotRules): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $reservationsByRoom = Reservation::query()
            ->whereIn('room_id', $rooms->pluck('id'))
            ->whereDate('reserved_date', $validated['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->get(['room_id', 'starts_at', 'ends_at'])
            ->groupBy('room_id');

        return new JsonResponse([
            'data' => $rooms
                ->map(fn (Room $room): array => $this->availabilityResponse(
                    $room,
                    $validated['date'],
                    $reservationsByRoom->get($room->id, collect()),
                    $slotRules,
                ))
                ->values(),
        ]);
    }

    public function store(Request $request, BookingSlotRules $slotRules, PublicBookingService $bookings): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'slug')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', Rule::in($slotRules->slotStarts())],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $endTime = $slotRules->endForStart($validated['start_time']);

        try {
            $reservation = $bookings->create($validated, $endTime);
        } catch (ReservationOverlapException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation),
        ], 201);
    }

    private function roomResponse(Room $room): array
    {
        return [
            'id' => $room->slug,
            'name' => $room->name,
            'capacity' => $room->capacity,
            'business_id' => $room->business_id,
            'location' => $room->location,
            'amenities' => $room->amenities ?? [],
            'accent' => $room->accent,
        ];
    }

    private function reservationResponse(Reservation $reservation): array
    {
        return [
            'id' => (string) $reservation->id,
            'room_id' => $reservation->room->slug,
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

    private function availabilityResponse(
        Room $room,
        string $date,
        Collection $reservations,
        BookingSlotRules $slotRules,
    ): array {
        $slots = collect($slotRules->slotStarts())
            ->map(function (string $start) use ($reservations, $slotRules): array {
                $end = $slotRules->endForStart($start);

                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => $start.' - '.$end,
                    'available' => ! $reservations->contains(
                        fn (Reservation $reservation): bool => $this->overlaps(
                            $start,
                            $end,
                            $this->formatTime($reservation->starts_at),
                            $this->formatTime($reservation->ends_at),
                        ),
                    ),
                ];
            })
            ->values();

        return [
            'room_id' => $room->slug,
            'date' => $date,
            'slots' => $slots,
        ];
    }

    private function formatTime(string $time): string
    {
        return substr($time, 0, 5);
    }

    private function overlaps(string $start, string $end, string $reservationStart, string $reservationEnd): bool
    {
        return $reservationStart < $end && $reservationEnd > $start;
    }
}
