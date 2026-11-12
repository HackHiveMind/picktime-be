<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationOverlapException;
use App\Models\Reservation;
use App\Services\AdminReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReservationWebController extends Controller
{
    public function store(Request $request, AdminReservationService $reservations): RedirectResponse
    {
        $validated = $this->validateReservationPayload($request);

        try {
            $reservations->create($validated);
        } catch (ReservationOverlapException $exception) {
            throw ValidationException::withMessages([
                'start_time' => $exception->getMessage(),
            ]);
        }

        return redirect('/admin')->with('status', 'Reservation created.');
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        $reservation->delete();

        return redirect('/admin')->with('status', 'Reservation deleted.');
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
            'status' => ['required', Rule::enum(ReservationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'locale' => ['sometimes', Rule::in(['ro', 'en', 'ru'])],
        ]);

        $validated['end_time'] ??= CarbonImmutable::createFromFormat('H:i', $validated['start_time'])
            ->addHour()
            ->format('H:i');

        if ($validated['end_time'] <= $validated['start_time']) {
            throw ValidationException::withMessages([
                'end_time' => 'End time must be after start time on the same day.',
            ]);
        }

        return $validated;
    }
}
