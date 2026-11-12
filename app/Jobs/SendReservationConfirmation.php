<?php

namespace App\Jobs;

use App\Enums\ReservationStatus;
use App\Mail\ReservationConfirmation;
use App\Models\Reservation;
use App\Services\ReservationCancellationLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReservationConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $reservationId, public string $version)
    {
        $this->afterCommit();
    }

    public function handle(ReservationCancellationLinks $links): void
    {
        $reservation = Reservation::query()->with('room')->find($this->reservationId);

        if (! $reservation || $reservation->status !== ReservationStatus::Confirmed
            || ! hash_equals($this->version, $links->version($reservation))
            || $links->startsAt($reservation)->isPast()) {
            return;
        }

        Mail::to($reservation->email)->send((new ReservationConfirmation($reservation, $links->url($reservation)))->locale($reservation->locale ?? 'ro'));
    }
}
