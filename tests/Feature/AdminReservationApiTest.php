<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReservationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_reservations(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet', 'name' => 'iMEET Room']);
        Reservation::factory()->for($room)->create([
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'status' => ReservationStatus::Pending,
        ]);

        $this->getJson('/api/admin/reservations?date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertJsonPath('data.0.room_id', 'imeet')
            ->assertJsonPath('data.0.room_name', 'iMEET Room')
            ->assertJsonPath('data.0.first_name', 'Ana')
            ->assertJsonPath('data.0.last_name', 'Popescu')
            ->assertJsonPath('data.0.email', 'ana@example.com')
            ->assertJsonPath('data.0.phone', '069123456')
            ->assertJsonPath('data.0.date', '2026-07-04')
            ->assertJsonPath('data.0.start_time', '13:00')
            ->assertJsonPath('data.0.end_time', '15:00')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_admin_can_create_reservation_with_custom_time_range(): void
    {
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/admin/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '13:00',
            'end_time' => '15:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ANA@EXAMPLE.COM',
            'phone' => '069123456',
            'status' => 'pending',
            'notes' => 'Admin booking',
        ])
            ->assertCreated()
            ->assertJsonPath('data.room_id', 'imeet')
            ->assertJsonPath('data.date', '2026-07-04')
            ->assertJsonPath('data.start_time', '13:00')
            ->assertJsonPath('data.end_time', '15:00')
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reservations', [
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'status' => 'pending',
        ]);
    }

    public function test_admin_create_reservation_rejects_overlapping_booking(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
        ]);

        $this->postJson('/api/admin/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Selected room is already reserved for this time range.');
    }
}
