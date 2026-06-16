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

    public function test_public_reservation_sends_guest_and_admin_emails_with_resend(): void
    {
        config([
            'services.resend.key' => 're_test_key',
            'services.resend.from' => 'iHUB Booking <bookings@example.com>',
            'services.resend.admin_to' => 'admin@example.com',
        ]);
        Http::fake([
            'api.resend.com/*' => Http::response([
                'data' => [
                    ['id' => 'guest-email-id'],
                    ['id' => 'admin-email-id'],
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

            return $request->url() === 'https://api.resend.com/emails/batch'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer re_test_key')
                && count($payload) === 2
                && $payload[0]['to'] === 'ana@gmail.com'
                && $payload[0]['from'] === 'iHUB Booking <bookings@example.com>'
                && $payload[0]['subject'] === 'Rezervarea ta iHUB este confirmata'
                && str_contains($payload[0]['html'], 'https://pictime-ihub-booking-fe.vercel.app/ihub-logo.png')
                && str_contains($payload[0]['html'], '#f7de05')
                && str_contains($payload[0]['html'], '#74bd45')
                && str_contains($payload[0]['html'], 'Detalii rezervare')
                && str_contains($payload[0]['html'], 'booking-detail-card')
                && str_contains($payload[0]['html'], 'Salut Ana,')
                && str_contains($payload[0]['html'], 'Rezervarea dumneavoastra')
                && str_contains($payload[0]['html'], 'Rezervati o sala de sedinte iMEET Room')
                && ! str_contains($payload[0]['html'], 'dashboard')
                && ! str_contains($payload[0]['html'], 'Rezervarea este inregistrata in sistemul iHUB')
                && str_contains($payload[0]['html'], 'iMEET Room')
                && $payload[1]['to'] === 'admin@example.com'
                && $payload[1]['subject'] === 'Rezervare noua iHUB'
                && str_contains($payload[1]['html'], 'Ana Popescu')
                && ! str_contains($payload[1]['html'], 'dashboard')
                && ! str_contains($payload[1]['html'], 'Rezervarea este inregistrata in sistemul iHUB');
        });
    }

    public function test_public_reservation_rejects_reserved_test_email_domains_before_resend(): void
    {
        config([
            'services.resend.key' => 're_test_key',
            'services.resend.from' => 'iHUB Booking <bookings@example.com>',
            'services.resend.admin_to' => 'admin@example.com',
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

    public function test_public_reservation_still_succeeds_when_resend_fails(): void
    {
        config([
            'services.resend.key' => 're_test_key',
            'services.resend.from' => 'iHUB Booking <bookings@example.com>',
            'services.resend.admin_to' => 'admin@example.com',
        ]);
        Http::fake([
            'api.resend.com/*' => Http::response(['message' => 'Rate limited'], 429),
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
        Http::assertSentCount(1);
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
