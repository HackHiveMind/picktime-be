<?php

namespace App\Http\Controllers\Api;

use App\Domain\Booking\BookingSlotRules;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return new JsonResponse([
            'data' => [
                'room_id' => $room->slug,
                'date' => $validated['date'],
                'slots' => $slots,
            ],
        ]);
    }

    public function store(
        Request $request,
        BookingSlotRules $slotRules,
        ReservationEmailService $reservationEmailService
    ): JsonResponse {
        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'slug')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', Rule::in($slotRules->slotStarts())],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->shouldValidateDeliverableEmailDomain()) {
                        return;
                    }

                    $domain = str((string) $value)->afterLast('@')->lower()->toString();

                    if (in_array($domain, ['example.com', 'example.net', 'example.org', 'test.com'], true)) {
                        $fail('Use a real email address so we can send the booking confirmation.');
                    }
                },
            ],
            'phone' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $room = Room::query()
            ->where('slug', $validated['room_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $endTime = $slotRules->endForStart($validated['start_time']);
        $alreadyReserved = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $validated['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->where('starts_at', '<', $endTime)
            ->where('ends_at', '>', $validated['start_time'])
            ->exists();

        if ($alreadyReserved) {
            return new JsonResponse([
                'message' => 'Selected room is already reserved for this time slot.',
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
            'status' => ReservationStatus::Confirmed,
            'ends_at' => $endTime,
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => str($validated['email'])->lower()->toString(),
            'phone' => trim($validated['phone']),
            'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
        ])->save();

        $reservationEmailService->sendPublicBookingEmails($reservation);

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation->load('room')),
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
            'image_url' => $room->image_url,
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

    private function formatTime(string $time): string
    {
        return substr($time, 0, 5);
    }

    private function shouldValidateDeliverableEmailDomain(): bool
    {
        return filled(config('mail.from.address'))
            && filled(config('services.booking_email.admin_to'));
    }

    private function overlaps(string $start, string $end, string $reservationStart, string $reservationEnd): bool
    {
        return $reservationStart < $end && $reservationEnd > $start;
    }
}
