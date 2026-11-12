<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\BookingSlotRules;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationOverlapException;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\PublicBookingService;
use App\Services\RoomAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicBookingController extends Controller
{
    private const MAX_RANGE_DAYS = 31;

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
        abort_unless($room->is_active, 404);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reservations = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $validated['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->get(['starts_at', 'ends_at']);

        $room->load(['blocks' => fn ($query) => $query->whereDate('blocked_date', $validated['date'])]);

        return new JsonResponse([
            'data' => $this->availabilityResponse($room, $validated['date'], $reservations, $slotRules),
        ]);
    }

    public function availabilityIndex(Request $request, BookingSlotRules $slotRules): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required_without:date_from', 'prohibits:date_from,date_to', 'date_format:Y-m-d'],
            'date_from' => ['required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'room_id' => ['nullable', Rule::exists('rooms', 'slug')->where('is_active', true)],
        ]);

        $from = $validated['date_from'] ?? $validated['date'];
        $to = $validated['date_to'] ?? $from;
        $firstDay = CarbonImmutable::parse($from);
        $lastDay = CarbonImmutable::parse($to);

        if ($firstDay->diffInDays($lastDay) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'date_to' => 'The date range may not exceed '.self::MAX_RANGE_DAYS.' days.',
            ]);
        }

        $until = $lastDay->addDay()->toDateString();
        $rooms = Room::query()
            ->where('is_active', true)
            ->with(['blocks' => fn ($query) => $query->where('blocked_date', '>=', $from)->where('blocked_date', '<', $until)])
            ->when(
                $validated['room_id'] ?? null,
                fn ($query, string $roomId) => $query->where('slug', $roomId),
            )
            ->orderBy('name')
            ->get();

        $reservationsByRoomAndDate = Reservation::query()
            ->whereIn('room_id', $rooms->pluck('id'))
            ->where('reserved_date', '>=', $from)
            ->where('reserved_date', '<', $until)
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->get(['room_id', 'reserved_date', 'starts_at', 'ends_at'])
            ->groupBy(fn (Reservation $reservation): string => $reservation->room_id.'|'.$reservation->reserved_date->toDateString());

        $data = [];

        for ($day = $firstDay; ($date = $day->toDateString()) <= $to; $day = $day->addDay()) {
            foreach ($rooms as $room) {
                $data[] = $this->availabilityResponse(
                    $room,
                    $date,
                    $reservationsByRoomAndDate->get($room->id.'|'.$date, collect()),
                    $slotRules,
                );
            }
        }

        return new JsonResponse(['data' => $data]);
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
            'locale' => ['nullable', Rule::in(['ro', 'en', 'ru'])],
        ]);

        $validated['locale'] ??= $request->getPreferredLanguage(['ro', 'en', 'ru']) ?? 'ro';

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
            ->map(function (string $start) use ($room, $date, $reservations, $slotRules): array {
                $end = $slotRules->endForStart($start);

                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => $start.' - '.$end,
                    'available' => app(RoomAvailabilityService::class)->permits($room, $date, $start, $end) && ! $reservations->contains(
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
