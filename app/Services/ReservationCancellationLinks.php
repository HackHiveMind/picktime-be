<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

class ReservationCancellationLinks
{
    public function version(Reservation $reservation): string
    {
        return hash('sha256', implode('|', [
            $reservation->email,
            $reservation->locale ?? 'ro',
            $reservation->room_id,
            $reservation->reserved_date->format('Y-m-d'),
            substr($reservation->starts_at, 0, 5),
            substr($reservation->ends_at, 0, 5),
            $reservation->created_at?->toISOString(),
        ]));
    }

    public function startsAt(Reservation $reservation): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $reservation->reserved_date->format('Y-m-d').' '.substr($reservation->starts_at, 0, 5),
            'Europe/Chisinau',
        );
    }

    public function url(Reservation $reservation): string
    {
        return URL::temporarySignedRoute('reservations.cancel', $this->startsAt($reservation), [
            'reservation' => $reservation->id,
            'version' => $this->version($reservation),
        ]);
    }
}
