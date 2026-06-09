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

    public function test_admin_list_excludes_cancelled_reservations_by_default(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet', 'name' => 'iMEET Room']);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '14:00',
            'status' => ReservationStatus::Cancelled,
        ]);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-07-04',
            'starts_at' => '14:00',
            'ends_at' => '15:00',
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->getJson('/api/admin/reservations?date_from=2026-07-04&date_to=2026-07-04')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.start_time', '14:00')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    public function test_admin_list_can_include_cancelled_reservations(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet', 'name' => 'iMEET Room']);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '14:00',
            'status' => ReservationStatus::Cancelled,
        ]);

        $this->getJson('/api/admin/reservations?include_cancelled=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'cancelled');
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

    public function test_admin_can_create_reservation_without_end_time(): void
    {
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/admin/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '13:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ANA@EXAMPLE.COM',
            'phone' => '069123456',
        ])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '13:00')
            ->assertJsonPath('data.end_time', '14:00');

        $this->assertDatabaseHas('reservations', [
            'starts_at' => '13:00',
            'ends_at' => '14:00',
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

    public function test_admin_can_rebook_cancelled_reservation_slot(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $reservation = Reservation::factory()->for($room)->create([
            'status' => ReservationStatus::Cancelled,
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '14:00',
            'first_name' => 'Old',
            'last_name' => 'Guest',
            'email' => 'old@example.com',
            'phone' => '000',
        ]);

        $this->postJson('/api/admin/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', (string) $reservation->id)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.first_name', 'Ana');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
            'email' => 'ana@example.com',
        ]);
    }

    public function test_admin_can_update_reservation(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $reservation = Reservation::factory()->for($room)->create([
            'first_name' => 'Ana',
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
        ]);

        $this->putJson("/api/admin/reservations/{$reservation->id}", [
            'room_id' => 'imeet',
            'date' => '2026-07-05',
            'start_time' => '10:00',
            'end_time' => '11:30',
            'first_name' => 'Maria',
            'last_name' => 'Ionescu',
            'email' => 'MARIA@EXAMPLE.COM',
            'phone' => '060000000',
            'status' => 'confirmed',
            'notes' => 'Moved by admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-07-05')
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.end_time', '11:30')
            ->assertJsonPath('data.first_name', 'Maria')
            ->assertJsonPath('data.email', 'maria@example.com')
            ->assertJsonPath('data.notes', 'Moved by admin');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'first_name' => 'Maria',
            'starts_at' => '10:00',
            'ends_at' => '11:30',
        ]);
        $this->assertSame('2026-07-05', $reservation->refresh()->reserved_date->format('Y-m-d'));
    }

    public function test_admin_update_reservation_rejects_overlapping_booking(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
        ]);
        $reservation = Reservation::factory()->for($room)->create([
            'reserved_date' => '2026-07-05',
            'starts_at' => '10:00',
            'ends_at' => '11:00',
        ]);

        $this->putJson("/api/admin/reservations/{$reservation->id}", [
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

    public function test_admin_can_cancel_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->patchJson("/api/admin/reservations/{$reservation->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_admin_can_delete_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $this->deleteJson("/api/admin/reservations/{$reservation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('reservations', [
            'id' => $reservation->id,
        ]);
    }
}
