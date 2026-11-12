<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationCancellationLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReservationCancellationController extends Controller
{
    public function show(Request $request, Reservation $reservation, ReservationCancellationLinks $links): View
    {
        $this->ensureValid($request, $reservation, $links);

        return view('reservations.cancel', ['reservation' => $reservation->load('room'), 'action' => $request->fullUrl()]);
    }

    public function cancel(Request $request, Reservation $reservation, ReservationCancellationLinks $links): View
    {
        $cancelled = DB::transaction(function () use ($request, $reservation, $links): Reservation {
            Room::query()->whereKey($reservation->room_id)->lockForUpdate()->firstOrFail();
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $this->ensureValid($request, $locked, $links);

            if ($locked->status !== ReservationStatus::Cancelled) {
                $locked->update(['status' => ReservationStatus::Cancelled]);
            }

            return $locked->load('room');
        });

        return view('reservations.cancel', ['reservation' => $cancelled, 'action' => $request->fullUrl()]);
    }

    private function ensureValid(Request $request, Reservation $reservation, ReservationCancellationLinks $links): void
    {
        abort_unless(hash_equals($links->version($reservation), (string) $request->query('version')), 403, __('bookings.invalid', [], $reservation->locale ?? 'ro'));
        abort_if($links->startsAt($reservation)->isPast(), 403, __('bookings.started', [], $reservation->locale ?? 'ro'));
    }
}
