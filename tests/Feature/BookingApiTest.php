<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_rooms_endpoint_returns_active_rooms(): void
    {
        Room::factory()->create([
            'name' => 'iMEET Room',
            'slug' => 'imeet',
            'capacity' => 8,
            'is_active' => true,
        ]);
        Room::factory()->create(['is_active' => false]);

        $this->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'imeet')
            ->assertJsonPath('data.0.name', 'iMEET Room')
            ->assertJsonPath('data.0.capacity', 8)
            ->assertJsonPath('data.0.business_id', 'chisinau');
    }

    public function test_public_booking_endpoints_remain_open_without_login(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);

        $this->getJson('/api/rooms')->assertOk();
        $this->getJson("/api/rooms/{$room->slug}/availability?date=2026-07-04")->assertOk();
    }

    public function test_availability_endpoint_marks_existing_reservations_unavailable(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        Reservation::factory()->for($room)->create([
            'status' => ReservationStatus::Confirmed,
            'reserved_date' => '2026-06-10',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);

        $this->getJson('/api/rooms/imeet/availability?date=2026-06-10')
            ->assertOk()
            ->assertJsonPath('data.room_id', 'imeet')
            ->assertJsonPath('data.date', '2026-06-10')
            ->assertJsonPath('data.slots.0.start', '09:00')
            ->assertJsonPath('data.slots.0.end', '10:00')
            ->assertJsonPath('data.slots.0.available', false)
            ->assertJsonPath('data.slots.1.start', '09:30')
            ->assertJsonPath('data.slots.1.end', '10:30')
            ->assertJsonPath('data.slots.1.available', false)
            ->assertJsonPath('data.slots.2.start', '10:00')
            ->assertJsonPath('data.slots.2.available', true);
    }

    public function test_public_reservation_endpoint_creates_a_reservation(): void
    {
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'Ana@Gmail.com',
            'phone' => '+373 600 00 000',
            'notes' => 'Project meeting',
        ])
            ->assertCreated()
            ->assertJsonPath('data.room_id', 'imeet')
            ->assertJsonPath('data.date', '2026-06-10')
            ->assertJsonPath('data.start_time', '09:00')
            ->assertJsonPath('data.end_time', '10:00')
            ->assertJsonPath('data.email', 'ana@gmail.com')
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('reservations', [
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);
    }

    public function test_public_reservation_sends_guest_and_admin_emails_with_sendgrid(): void
    {
        config([
            'services.booking_email.driver' => 'sendgrid',
            'services.booking_email.admin_to' => 'admin@example.com',
            'services.sendgrid.key' => 'sg_test_key',
            'services.sendgrid.from_address' => 'booking@ihub.test',
            'services.sendgrid.from_name' => 'iHUB Booking',
        ]);
        Http::fake([
            'api.sendgrid.com/*' => Http::response(null, 202),
        ]);
        Room::factory()->create([
            'name' => 'iMEET Room',
            'slug' => 'imeet',
        ]);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'Ana@Gmail.com',
            'phone' => '+373 600 00 000',
        ])->assertCreated();

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $text = $payload['content'][0]['value'] ?? '';
            $html = $payload['content'][1]['value'] ?? '';

            return $request->url() === 'https://api.sendgrid.com/v3/mail/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer sg_test_key')
                && ($payload['from']['email'] ?? null) === 'booking@ihub.test'
                && ($payload['personalizations'][0]['to'][0]['email'] ?? null) === 'ana@gmail.com'
                && ($payload['subject'] ?? null) === 'Rezervarea ta iHUB este confirmata'
                && ($payload['content'][0]['type'] ?? null) === 'text/plain'
                && ($payload['content'][1]['type'] ?? null) === 'text/html'
                && str_contains($text, 'Rezervarea ta este confirmata.')
                && str_contains($html, 'data:image/png;base64,')
                && str_contains($html, 'Sala: iMEET Room')
                && str_contains($html, 'Data rezervarii: 2026-06-10')
                && str_contains($html, 'Ora: 09:00 - 10:00')
                && str_contains($html, 'Multumim,<br>iHUB Chisinau.');
        });
        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $html = $payload['content'][1]['value'] ?? '';

            return $request->url() === 'https://api.sendgrid.com/v3/mail/send'
                && ($payload['personalizations'][0]['to'][0]['email'] ?? null) === 'admin@example.com'
                && ($payload['subject'] ?? null) === 'Rezervare noua iHUB'
                && ($payload['content'][0]['type'] ?? null) === 'text/plain'
                && ($payload['content'][1]['type'] ?? null) === 'text/html'
                && str_contains($html, 'Ana Popescu')
                && str_contains($html, 'Sala: iMEET Room');
        });
    }

    public function test_public_reservation_sends_guest_and_admin_emails_with_mailjet(): void
    {
        config([
            'services.booking_email.driver' => 'mailjet',
            'services.mailjet.key' => 'mj_test_key',
            'services.mailjet.secret' => 'mj_test_secret',
            'services.mailjet.from_address' => 'booking@ihub.test',
            'services.mailjet.from_name' => 'iHUB Booking',
            'services.booking_email.admin_to' => 'admin@example.com',
        ]);
        Http::fake([
            'api.mailjet.com/*' => Http::response([
                'Messages' => [
                    ['Status' => 'success'],
                    ['Status' => 'success'],
                ],
            ]),
        ]);
        Room::factory()->create([
            'name' => 'iMEET Room',
            'slug' => 'imeet',
        ]);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'Ana@Gmail.com',
            'phone' => '+373 600 00 000',
        ])->assertCreated();

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $messages = $payload['Messages'] ?? [];
            $guest = $messages[0] ?? [];
            $admin = $messages[1] ?? [];
            $guestHtml = $guest['HTMLPart'] ?? '';
            $adminHtml = $admin['HTMLPart'] ?? '';

            return $request->url() === 'https://api.mailjet.com/v3.1/send'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('mj_test_key:mj_test_secret'))
                && count($messages) === 2
                && ($guest['From']['Email'] ?? null) === 'booking@ihub.test'
                && ($guest['To'][0]['Email'] ?? null) === 'ana@gmail.com'
                && ($guest['Subject'] ?? null) === 'Rezervarea ta iHUB este confirmata'
                && str_contains($guestHtml, 'data:image/png;base64,')
                && str_contains($guestHtml, '#f7de05')
                && str_contains($guestHtml, '#74bd45')
                && str_contains($guestHtml, 'Detalii rezervare')
                && str_contains($guestHtml, 'booking-detail-card')
                && str_contains($guestHtml, 'Sala: iMEET Room')
                && str_contains($guestHtml, 'Data rezervarii: 2026-06-10')
                && str_contains($guestHtml, 'Ora: 09:00 - 10:00')
                && str_contains($guestHtml, 'Multumim,<br>iHUB Chisinau.')
                && ! str_contains($guestHtml, 'Rezervarea dumneavoastra')
                && ! str_contains($guestHtml, 'Ora Europei de Est')
                && ! str_contains($guestHtml, 'dashboard')
                && ! str_contains($guestHtml, 'Rezervarea este inregistrata in sistemul iHUB')
                && ($admin['To'][0]['Email'] ?? null) === 'admin@example.com'
                && ($admin['Subject'] ?? null) === 'Rezervare noua iHUB'
                && str_contains($adminHtml, 'Ana Popescu')
                && strpos($adminHtml, 'Sala: iMEET Room') < strpos($adminHtml, 'Client: Ana Popescu')
                && str_contains($adminHtml, 'Data rezervarii: 2026-06-10')
                && str_contains($adminHtml, 'Ora: 09:00 - 10:00')
                && str_contains($adminHtml, 'Multumim,<br>iHUB Chisinau.')
                && ! str_contains($adminHtml, 'dashboard')
                && ! str_contains($adminHtml, 'Rezervarea este inregistrata in sistemul iHUB');
        });
    }

    public function test_public_reservation_rejects_reserved_test_email_domains_before_email_send(): void
    {
        config([
            'services.booking_email.driver' => 'mailjet',
            'services.mailjet.key' => 'mj_test_key',
            'services.mailjet.secret' => 'mj_test_secret',
            'services.mailjet.from_address' => 'booking@ihub.test',
            'services.booking_email.admin_to' => 'admin@example.com',
        ]);
        Http::fake();
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('reservations', [
            'email' => 'ana@gmail.com',
        ]);
    }

    public function test_public_reservation_still_succeeds_when_mailer_fails(): void
    {
        config([
            'services.booking_email.driver' => 'mailjet',
            'services.mailjet.key' => 'mj_test_key',
            'services.mailjet.secret' => 'mj_test_secret',
            'services.mailjet.from_address' => 'booking@ihub.test',
            'services.booking_email.admin_to' => 'admin@example.com',
        ]);
        Http::fake([
            'api.mailjet.com/*' => Http::response(['Messages' => [['Status' => 'error']]], 400),
        ]);
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'phone' => '+373 600 00 000',
        ])->assertCreated();

        $this->assertDatabaseHas('reservations', [
            'email' => 'ana@gmail.com',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);
    }

    public function test_public_reservation_endpoint_accepts_half_hour_start_times(): void
    {
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:30',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '09:30')
            ->assertJsonPath('data.end_time', '10:30');

        $this->assertDatabaseHas('reservations', [
            'starts_at' => '09:30',
            'ends_at' => '10:30',
        ]);
    }

    public function test_public_reservation_endpoint_rejects_overlapping_half_hour_booking(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-06-10',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:30',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Selected room is already reserved for this time slot.');
    }

    public function test_public_reservation_endpoint_rejects_double_booking(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-06-10',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Selected room is already reserved for this time slot.');
    }

    public function test_public_reservation_endpoint_can_rebook_cancelled_slot(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $reservation = Reservation::factory()->for($room)->create([
            'status' => ReservationStatus::Cancelled,
            'reserved_date' => '2026-06-10',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'first_name' => 'Old',
            'last_name' => 'Guest',
            'email' => 'old@example.com',
            'phone' => '000',
        ]);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', (string) $reservation->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.email', 'ana@gmail.com');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
            'email' => 'ana@gmail.com',
        ]);
    }

    public function test_public_reservation_validation_errors_return_unprocessable_json(): void
    {
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-06-10',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@gmail.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The start time field is required.');
    }
}
