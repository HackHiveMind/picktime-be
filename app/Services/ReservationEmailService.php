<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReservationEmailService
{
    public function sendPublicBookingEmails(Reservation $reservation): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $reservation->loadMissing('room');

        try {
            $response = Http::withToken((string) config('services.resend.key'))
                ->acceptJson()
                ->post('https://api.resend.com/emails/batch', [
                    $this->guestEmailPayload($reservation),
                    $this->adminEmailPayload($reservation),
                ]);

            if ($response->failed()) {
                Log::warning('Resend booking email request failed.', [
                    'reservation_id' => $reservation->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Resend booking email request threw an exception.', [
                'reservation_id' => $reservation->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.resend.key'))
            && filled(config('services.resend.from'))
            && filled(config('services.resend.admin_to'));
    }

    private function guestEmailPayload(Reservation $reservation): array
    {
        return [
            'from' => config('services.resend.from'),
            'to' => $reservation->email,
            'subject' => 'Rezervarea ta iHUB este confirmata',
            'html' => $this->guestHtml($reservation),
            'text' => $this->guestText($reservation),
        ];
    }

    private function adminEmailPayload(Reservation $reservation): array
    {
        return [
            'from' => config('services.resend.from'),
            'to' => config('services.resend.admin_to'),
            'subject' => 'Rezervare noua iHUB',
            'html' => $this->adminHtml($reservation),
            'text' => $this->adminText($reservation),
        ];
    }

    private function guestHtml(Reservation $reservation): string
    {
        $name = e($reservation->first_name);
        $room = e($reservation->room->name);
        $date = e($reservation->reserved_date->format('Y-m-d'));
        $time = e($this->timeRange($reservation));

        return <<<HTML
            <h1>Rezervarea ta este confirmata</h1>
            <p>Buna, {$name}.</p>
            <p>Am confirmat rezervarea pentru <strong>{$room}</strong>.</p>
            <p><strong>Data:</strong> {$date}<br><strong>Ora:</strong> {$time}</p>
            <p>Multumim,<br>iHUB Moldova</p>
        HTML;
    }

    private function adminHtml(Reservation $reservation): string
    {
        $guest = e($reservation->first_name.' '.$reservation->last_name);
        $room = e($reservation->room->name);
        $date = e($reservation->reserved_date->format('Y-m-d'));
        $time = e($this->timeRange($reservation));
        $email = e($reservation->email);
        $phone = e($reservation->phone);

        return <<<HTML
            <h1>Rezervare noua</h1>
            <p><strong>Client:</strong> {$guest}</p>
            <p><strong>Sala:</strong> {$room}<br><strong>Data:</strong> {$date}<br><strong>Ora:</strong> {$time}</p>
            <p><strong>Email:</strong> {$email}<br><strong>Telefon:</strong> {$phone}</p>
        HTML;
    }

    private function guestText(Reservation $reservation): string
    {
        return implode(PHP_EOL, [
            'Rezervarea ta este confirmata.',
            'Buna, '.$reservation->first_name.'.',
            'Sala: '.$reservation->room->name,
            'Data: '.$reservation->reserved_date->format('Y-m-d'),
            'Ora: '.$this->timeRange($reservation),
            'Multumim, iHUB Moldova',
        ]);
    }

    private function adminText(Reservation $reservation): string
    {
        return implode(PHP_EOL, [
            'Rezervare noua iHUB.',
            'Client: '.$reservation->first_name.' '.$reservation->last_name,
            'Sala: '.$reservation->room->name,
            'Data: '.$reservation->reserved_date->format('Y-m-d'),
            'Ora: '.$this->timeRange($reservation),
            'Email: '.$reservation->email,
            'Telefon: '.$reservation->phone,
        ]);
    }

    private function timeRange(Reservation $reservation): string
    {
        return substr($reservation->starts_at, 0, 5).' - '.substr($reservation->ends_at, 0, 5);
    }
}
