<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_batch_availability_endpoint_returns_active_room_slots_in_one_response(): void
    {
        $brainstorm = Room::factory()->create([
            'name' => 'Book Brainstorm',
            'slug' => 'brainstorm',
            'is_active' => true,
        ]);
        Room::factory()->create([
            'slug' => 'offline-room',
            'is_active' => false,
        ]);
        Room::factory()->create([
            'name' => 'iMEET Room',
            'slug' => 'imeet',
            'is_active' => true,
        ]);
        Reservation::factory()->for($brainstorm)->create([
            'status' => ReservationStatus::Confirmed,
            'reserved_date' => '2026-06-24',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);

        $this->getJson('/api/availability?date=2026-06-24')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.room_id', 'brainstorm')
            ->assertJsonPath('data.0.date', '2026-06-24')
            ->assertJsonPath('data.0.slots.0.available', false)
            ->assertJsonPath('data.0.slots.1.available', false)
            ->assertJsonPath('data.0.slots.2.available', true)
            ->assertJsonPath('data.1.room_id', 'imeet')
            ->assertJsonPath('data.1.slots.0.available', true)
            ->assertJsonPath('data.1.slots.1.available', true);
    }

    public function test_batch_availability_endpoint_returns_a_whole_date_range_in_one_response(): void
    {
        $brainstorm = Room::factory()->create([
            'name' => 'Book Brainstorm',
            'slug' => 'brainstorm',
            'is_active' => true,
        ]);
        Room::factory()->create([
            'name' => 'iMEET Room',
            'slug' => 'imeet',
            'is_active' => true,
        ]);
        // Booked only on the second day.
        Reservation::factory()->for($brainstorm)->create([
            'status' => ReservationStatus::Confirmed,
            'reserved_date' => '2026-06-25',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ]);

        $this->getJson('/api/availability?date_from=2026-06-24&date_to=2026-06-25')
            ->assertOk()
            ->assertJsonCount(4, 'data') // 2 days x 2 rooms, date-major then room order
            ->assertJsonPath('data.0.room_id', 'brainstorm')
            ->assertJsonPath('data.0.date', '2026-06-24')
            ->assertJsonPath('data.0.slots.0.available', true) // free on the 24th
            ->assertJsonPath('data.2.room_id', 'brainstorm')
            ->assertJsonPath('data.2.date', '2026-06-25')
            ->assertJsonPath('data.2.slots.0.available', false); // booked on the 25th
    }

    public function test_batch_availability_endpoint_rejects_an_excessive_date_range(): void
    {
        Room::factory()->create(['is_active' => true]);

        $this->getJson('/api/availability?date_from=2026-06-01&date_to=2026-08-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
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
            'email' => 'Ana@Example.com',
            'phone' => '+373 600 00 000',
            'notes' => 'Project meeting',
        ])
            ->assertCreated()
            ->assertJsonPath('data.room_id', 'imeet')
            ->assertJsonPath('data.date', '2026-06-10')
            ->assertJsonPath('data.start_time', '09:00')
            ->assertJsonPath('data.end_time', '10:00')
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('reservations', [
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
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
            'email' => 'ana@example.com',
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
            'email' => 'ana@example.com',
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
            'email' => 'ana@example.com',
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
            'email' => 'ana@example.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', (string) $reservation->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.email', 'ana@example.com');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
            'email' => 'ana@example.com',
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
            'email' => 'ana@example.com',
            'phone' => '+373 600 00 000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The start time field is required.');
    }
}
