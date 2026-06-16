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

        return $this->brandedHtml(
            title: 'Rezervarea ta este confirmata',
            eyebrow: 'Booking confirmat',
            intro: "Buna, {$name}. Am confirmat rezervarea ta la iHUB Moldova.",
            rows: [
                'Sala' => $room,
                'Data' => $date,
                'Ora' => $time,
            ],
            footer: 'Te asteptam la iHUB. Daca ai nevoie de modificari, contacteaza echipa iHUB Moldova.'
        );
    }

    private function adminHtml(Reservation $reservation): string
    {
        $guest = e($reservation->first_name.' '.$reservation->last_name);
        $room = e($reservation->room->name);
        $date = e($reservation->reserved_date->format('Y-m-d'));
        $time = e($this->timeRange($reservation));
        $email = e($reservation->email);
        $phone = e($reservation->phone);

        return $this->brandedHtml(
            title: 'Rezervare noua',
            eyebrow: 'Admin notification',
            intro: 'A fost creata o rezervare noua in calendarul iHUB.',
            rows: [
                'Client' => $guest,
                'Sala' => $room,
                'Data' => $date,
                'Ora' => $time,
                'Email' => '<a href="mailto:'.$email.'" style="color:#111827;text-decoration:underline;">'.$email.'</a>',
                'Telefon' => $phone,
            ],
            footer: 'Verifica dashboard-ul admin pentru detalii si modificari.'
        );
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

    private function brandedHtml(string $title, string $eyebrow, string $intro, array $rows, string $footer): string
    {
        $logoUrl = e((string) config('services.resend.logo_url'));
        $detailsRows = collect($rows)
            ->map(fn (string $value, string $label): string => $this->detailRow($label, $value))
            ->implode('');

        return <<<HTML
            <!doctype html>
            <html>
            <body style="margin:0;background:#f4f4f0;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;color:#111827;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                <tr>
                  <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;">
                      <tr>
                        <td style="background:#050505;padding:26px 30px;">
                          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                            <tr>
                              <td style="vertical-align:middle;">
                                <img src="{$logoUrl}" alt="iHUB Moldova" width="92" style="display:block;border:0;outline:none;text-decoration:none;border-radius:10px;">
                              </td>
                              <td align="right" style="vertical-align:middle;">
                                <span style="display:inline-block;background:#f7de05;color:#050505;border-radius:999px;padding:8px 12px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">{$eyebrow}</span>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:34px 30px 12px;">
                          <h1 style="margin:0;font-size:30px;line-height:1.15;font-weight:800;color:#111827;">{$title}</h1>
                          <p style="margin:14px 0 0;font-size:16px;line-height:1.6;color:#4b5563;">{$intro}</p>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:16px 30px 8px;">
                          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0;background:#fafafa;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
                            {$detailsRows}
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:18px 30px 34px;">
                          <div style="border-left:5px solid #74bd45;background:#f0f9ec;border-radius:12px;padding:14px 16px;color:#183b12;font-size:14px;line-height:1.55;">{$footer}</div>
                        </td>
                      </tr>
                      <tr>
                        <td style="background:#050505;padding:18px 30px;color:#d1d5db;font-size:12px;line-height:1.5;">
                          iHUB Moldova · Meeting room booking
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </body>
            </html>
        HTML;
    }

    private function detailRow(string $label, string $value): string
    {
        $label = e($label);

        return <<<HTML
            <tr>
              <td style="width:34%;padding:14px 16px;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">{$label}</td>
              <td style="padding:14px 16px;border-bottom:1px solid #e5e7eb;color:#111827;font-size:15px;font-weight:700;">{$value}</td>
            </tr>
        HTML;
    }
}
