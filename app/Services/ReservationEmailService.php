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
            if ($this->usesSendGrid()) {
                $this->sendWithSendGrid($reservation);

                return;
            }

            $this->sendWithMailjet($reservation);
        } catch (Throwable $exception) {
            Log::warning('Booking email request failed.', [
                'reservation_id' => $reservation->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isConfigured(): bool
    {
        if ($this->usesSendGrid()) {
            return filled(config('services.sendgrid.key'))
                && filled(config('services.sendgrid.from_address'))
                && filled(config('services.booking_email.admin_to'));
        }

        return filled(config('services.mailjet.key'))
            && filled(config('services.mailjet.secret'))
            && filled(config('services.mailjet.from_address'))
            && filled(config('services.booking_email.admin_to'));
    }

    private function usesSendGrid(): bool
    {
        return config('services.booking_email.driver') === 'sendgrid';
    }

    private function sendWithMailjet(Reservation $reservation): void
    {
        $response = Http::withBasicAuth((string) config('services.mailjet.key'), (string) config('services.mailjet.secret'))
            ->acceptJson()
            ->post('https://api.mailjet.com/v3.1/send', [
                'Messages' => [
                    $this->guestMessage($reservation),
                    $this->adminMessage($reservation),
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Mailjet booking email request failed.', [
                'reservation_id' => $reservation->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function sendWithSendGrid(Reservation $reservation): void
    {
        foreach ($this->sendGridMessages($reservation) as $message) {
            $response = Http::withToken((string) config('services.sendgrid.key'))
                ->acceptJson()
                ->post('https://api.sendgrid.com/v3/mail/send', $message);

            if ($response->failed()) {
                Log::warning('SendGrid booking email request failed.', [
                    'reservation_id' => $reservation->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        }
    }

    private function guestMessage(Reservation $reservation): array
    {
        return [
            'From' => $this->fromAddress(),
            'To' => [
                ['Email' => $reservation->email],
            ],
            'Subject' => 'Rezervarea ta iHUB este confirmata',
            'HTMLPart' => $this->guestHtml($reservation),
            'TextPart' => $this->guestText($reservation),
        ];
    }

    private function adminMessage(Reservation $reservation): array
    {
        return [
            'From' => $this->fromAddress(),
            'To' => [
                ['Email' => (string) config('services.booking_email.admin_to')],
            ],
            'Subject' => 'Rezervare noua iHUB',
            'HTMLPart' => $this->adminHtml($reservation),
            'TextPart' => $this->adminText($reservation),
        ];
    }

    private function fromAddress(): array
    {
        return [
            'Email' => (string) config('services.mailjet.from_address'),
            'Name' => (string) config('services.mailjet.from_name', 'iHUB Booking'),
        ];
    }

    private function sendGridMessages(Reservation $reservation): array
    {
        return [
            $this->sendGridMessage(
                to: $reservation->email,
                subject: 'Rezervarea ta iHUB este confirmata',
                html: $this->guestHtml($reservation),
                text: $this->guestText($reservation)
            ),
            $this->sendGridMessage(
                to: (string) config('services.booking_email.admin_to'),
                subject: 'Rezervare noua iHUB',
                html: $this->adminHtml($reservation),
                text: $this->adminText($reservation)
            ),
        ];
    }

    private function sendGridMessage(string $to, string $subject, string $html, string $text): array
    {
        return [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => $to],
                    ],
                ],
            ],
            'from' => [
                'email' => (string) config('services.sendgrid.from_address'),
                'name' => (string) config('services.sendgrid.from_name', 'iHUB Booking'),
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $text,
                ],
                [
                    'type' => 'text/html',
                    'value' => $html,
                ],
            ],
        ];
    }

    private function guestHtml(Reservation $reservation): string
    {
        $name = e($reservation->first_name);

        return $this->brandedHtml(
            title: 'Rezervarea ta este confirmata',
            eyebrow: 'Booking confirmat',
            intro: "Salut {$name},<br><br>Rezervarea ta a fost confirmata.",
            detailsHtml: $this->confirmationDetailsHtml($reservation, includeGuest: false),
            footer: ''
        );
    }

    private function adminHtml(Reservation $reservation): string
    {
        return $this->brandedHtml(
            title: 'Rezervare noua',
            eyebrow: 'Admin notification',
            intro: 'Salut Receptie iHUB,<br><br>A fost creata o rezervare noua. Iata detaliile rezervarii:',
            detailsHtml: $this->confirmationDetailsHtml($reservation, includeGuest: true),
            footer: ''
        );
    }

    private function guestText(Reservation $reservation): string
    {
        return implode(PHP_EOL, [
            'Rezervarea ta este confirmata.',
            'Buna, '.$reservation->first_name.'.',
            'Sala: '.$reservation->room->name,
            'Data rezervarii: '.$reservation->reserved_date->format('Y-m-d'),
            'Ora: '.$this->timeRange($reservation),
            'Multumim, iHUB Chisinau',
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

    private function brandedHtml(string $title, string $eyebrow, string $intro, string $detailsHtml, string $footer): string
    {
        $logoUrl = e($this->logoSource());
        $footerHtml = $footer === '' ? '' : <<<HTML
                      <tr>
                        <td style="padding:12px 32px 34px;">
                          <div style="border-left:5px solid #74bd45;background:#f0f9ec;border-radius:10px;padding:14px 16px;color:#183b12;font-size:14px;line-height:1.55;">{$footer}</div>
                        </td>
                      </tr>
        HTML;

        return <<<HTML
            <!doctype html>
            <html>
            <body style="margin:0;background:#f4f4f0;padding:32px 16px;font-family:Arial,Helvetica,sans-serif;color:#111827;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                <tr>
                  <td align="center">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
                      <tr>
                        <td style="background:#050505;padding:24px 32px 22px;">
                          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                            <tr>
                              <td style="vertical-align:middle;">
                                <img src="{$logoUrl}" alt="iHUB Moldova" width="82" style="display:block;border:0;outline:none;text-decoration:none;border-radius:10px;">
                              </td>
                              <td align="right" style="vertical-align:middle;">
                                <span style="display:inline-block;background:#f7de05;color:#050505;border-radius:999px;padding:9px 13px;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">{$eyebrow}</span>
                              </td>
                            </tr>
                          </table>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:34px 32px 18px;text-align:left;">
                          <p style="margin:0 0 10px;color:#74bd45;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;">iHUB Moldova</p>
                          <h1 style="margin:0;font-size:32px;line-height:1.15;font-weight:800;color:#111827;">{$title}</h1>
                          <p style="margin:14px 0 0;font-size:16px;line-height:1.6;color:#4b5563;">{$intro}</p>
                        </td>
                      </tr>
                      <tr>
                        <td style="padding:8px 32px 14px;">
                          <p style="margin:0 0 12px;font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;">Detalii rezervare</p>
                          <div class="booking-detail-card" style="background:#fbfbf8;border:1px solid #e5e7eb;border-radius:14px;padding:22px 24px;color:#111827;font-size:15px;line-height:1.65;">
                            {$detailsHtml}
                          </div>
                        </td>
                      </tr>
                      {$footerHtml}
                      <tr>
                        <td style="background:#050505;padding:18px 32px;color:#d1d5db;font-size:12px;line-height:1.5;">
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

    private function confirmationDetailsHtml(Reservation $reservation, bool $includeGuest): string
    {
        $room = e($reservation->room->name);
        $date = e($reservation->reserved_date->format('Y-m-d'));
        $time = e($this->timeRange($reservation));
        $guest = e($reservation->first_name.' '.$reservation->last_name);
        $email = e($reservation->email);
        $phone = e($reservation->phone);
        $guestDetails = $includeGuest
            ? '<p style="margin:22px 0 0;color:#4b5563;"><strong>Client: '.$guest.'</strong><br><strong>Email:</strong> <a href="mailto:'.$email.'" style="color:#111827;text-decoration:underline;">'.$email.'</a><br><strong>Telefon:</strong> '.$phone.'</p>'
            : '';

        return <<<HTML
            <p style="margin:0 0 10px;"><strong>Sala: {$room}</strong></p>
            <p style="margin:0 0 10px;"><strong>Data rezervarii: {$date}</strong></p>
            <p style="margin:0 0 24px;"><strong>Ora: {$time}</strong></p>
            <p style="margin:0;">Multumim,<br>iHUB Chisinau.</p>
            {$guestDetails}
        HTML;
    }

    private function logoSource(): string
    {
        $logoPath = public_path('ihub-logo.png');

        if (is_file($logoPath)) {
            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }

        return (string) config('services.booking_email.logo_url');
    }
}
