<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReservationWebController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateReservationPayload($request);

        $room = Room::query()
            ->where('slug', $validated['room_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if ($this->hasOverlap($room, $validated)) {
            throw ValidationException::withMessages([
                'start_time' => 'Selected room is already reserved for this time range.',
            ]);
        }

        Reservation::query()->create([
            'room_id' => $room->id,
            'reserved_date' => $validated['date'],
            'starts_at' => $validated['start_time'],
            'ends_at' => $validated['end_time'],
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'email' => str($validated['email'])->lower()->toString(),
            'phone' => trim($validated['phone']),
            'status' => $validated['status'],
            'notes' => isset($validated['notes']) ? trim($validated['notes']) : null,
        ]);

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
        ]);

        $validated['end_time'] ??= CarbonImmutable::createFromFormat('H:i', $validated['start_time'])
            ->addHour()
            ->format('H:i');

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function hasOverlap(Room $room, array $validated): bool
    {
        return Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reserved_date', $validated['date'])
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->where('starts_at', '<', $validated['end_time'])
            ->where('ends_at', '>', $validated['start_time'])
            ->exists();
    }
}
