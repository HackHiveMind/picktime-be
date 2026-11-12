<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ReservationConfirmation extends Mailable
{
    public function __construct(public Reservation $reservation, public string $cancellationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('bookings.subject', [], $this->reservation->locale ?? 'ro'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.reservation-confirmation');
    }
}
