<?php

namespace App\Http\Controllers;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): RedirectResponse|View
    {
        if (config('app.admin_dashboard_url', '/admin') !== '/admin') {
            return redirect(config('app.admin_dashboard_url'));
        }

        $reservations = Reservation::query()
            ->with('room')
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->orderBy('reserved_date')
            ->orderBy('starts_at')
            ->get();

        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', [
            'reservations' => $reservations,
            'rooms' => $rooms,
            'statuses' => ReservationStatus::cases(),
        ]);
    }
}
