<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationOverlapException;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\AdminReservationService;
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
            'include_cancelled' => ['nullable', Rule::in(['true', 'false', '1', '0', true, false, 1, 0])],
        ]);

        $includeCancelled = $request->boolean('include_cancelled');

        $reservations = Reservation::query()
            ->with('room')
            ->when(
                ! $includeCancelled,
                fn ($query) => $query->where('status', '!=', ReservationStatus::Cancelled),
            )
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

    public function store(Request $request, AdminReservationService $reservations): JsonResponse
    {
        $validated = $this->validateReservationPayload($request);

        try {
            $reservation = $reservations->create($validated);
        } catch (ReservationOverlapException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation),
        ], 201);
    }

    public function update(Request $request, Reservation $reservation, AdminReservationService $reservations): JsonResponse
    {
        $validated = $this->validateReservationPayload($request);

        try {
            $reservation = $reservations->update($reservation, $validated);
        } catch (ReservationOverlapException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation),
        ]);
    }

    public function cancel(Reservation $reservation, AdminReservationService $reservations): JsonResponse
    {
        $reservation = $reservations->cancel($reservation);

        return new JsonResponse([
            'data' => $this->reservationResponse($reservation),
        ]);
    }

    public function destroy(Reservation $reservation, AdminReservationService $reservations): Response
    {
        $reservations->delete($reservation);

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
