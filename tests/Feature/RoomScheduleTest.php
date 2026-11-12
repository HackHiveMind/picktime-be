<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_and_block_management_require_authentication(): void
    {
        $room = Room::factory()->create();
        $this->getJson('/api/admin/room-blocks')->assertUnauthorized();
        $this->postJson('/api/admin/room-blocks', [])->assertUnauthorized();
        $this->getJson("/api/admin/rooms/{$room->slug}/schedule")->assertUnauthorized();
        $this->putJson("/api/admin/rooms/{$room->slug}/schedule", [])->assertUnauthorized();
    }

    public function test_schedule_closes_days_and_limits_intervals_in_both_availability_endpoints(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        $hours = array_fill(0, 7, ['open' => '10:00', 'close' => '17:00']);
        $hours[1] = null;
        $this->putJson('/api/admin/rooms/imeet/schedule', ['opening_hours' => $hours])->assertOk();
        $this->getJson('/api/admin/rooms/imeet/schedule')->assertJsonPath('data.opening_hours', $hours);

        $this->getJson('/api/rooms/imeet/availability?date=2030-11-04')
            ->assertJsonPath('data.slots.0.available', false)
            ->assertJsonPath('data.slots.2.available', true)
            ->assertJsonPath('data.slots.15.available', false);
        $this->getJson('/api/availability?date=2030-11-05')
            ->assertJsonPath('data.0.slots.2.available', false);
        $this->postJson('/api/reservations', $this->booking($room, '2030-11-05'))->assertUnprocessable();
        $this->postJson('/api/admin/reservations', $this->booking($room, '2030-11-04', '09:30'))->assertUnprocessable();
    }

    public function test_invalid_schedules_and_block_ranges_are_rejected(): void
    {
        Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        foreach ([[], array_fill(0, 7, ['open' => '18:00', 'close' => '10:00']), array_fill(0, 7, ['open' => '08:00', 'close' => '22:00'])] as $hours) {
            $this->putJson('/api/admin/rooms/imeet/schedule', ['opening_hours' => $hours])->assertUnprocessable();
        }
        $this->postJson('/api/admin/room-blocks', ['room_id' => 'imeet', 'date' => '2030-11-04', 'start_time' => '12:00', 'end_time' => '11:00'])
            ->assertUnprocessable();
    }

    public function test_range_availability_combines_reservations_blocks_and_opening_hours_by_date(): void
    {
        $hours = array_fill(0, 7, ['open' => '09:00', 'close' => '21:00']);
        $hours[1] = null;
        $room = Room::factory()->create(['slug' => 'imeet', 'opening_hours' => $hours]);
        Room::factory()->create();
        $room->blocks()->create([
            'blocked_date' => '2030-11-04', 'starts_at' => '10:00', 'ends_at' => '11:00',
            'notes' => 'Internal maintenance',
        ]);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2030-11-06', 'starts_at' => '11:00', 'ends_at' => '12:00',
            'status' => 'confirmed',
        ]);
        Reservation::factory()->for($room)->create([
            'reserved_date' => '2030-11-06', 'starts_at' => '10:00', 'ends_at' => '11:00',
            'status' => 'cancelled',
        ]);

        $this->getJson('/api/availability?date_from=2030-11-04&date_to=2030-11-06&room_id=imeet')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.date', '2030-11-04')
            ->assertJsonPath('data.0.slots.2.available', false)
            ->assertJsonPath('data.0.slots.4.available', true)
            ->assertJsonPath('data.1.date', '2030-11-05')
            ->assertJsonPath('data.1.slots.0.available', false)
            ->assertJsonPath('data.2.date', '2030-11-06')
            ->assertJsonPath('data.2.slots.2.available', true)
            ->assertJsonPath('data.2.slots.4.available', false)
            ->assertDontSee('Internal maintenance');
    }

    public function test_availability_rejects_incomplete_reversed_and_oversized_ranges(): void
    {
        $this->getJson('/api/availability?date_from=2030-11-04')->assertJsonValidationErrors('date_to');
        $this->getJson('/api/availability?date_from=2030-11-04&date_to=2030-11-03')->assertJsonValidationErrors('date_to');
        $this->getJson('/api/availability?date_from=2030-11-01&date_to=2030-12-02')->assertJsonValidationErrors('date_to');
        $this->getJson('/api/availability?date=2030-11-01&date_from=2030-11-01&date_to=2030-11-02')->assertJsonValidationErrors('date');
    }

    public function test_block_is_shared_date_specific_and_enforced_on_public_admin_and_web_bookings(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        $identifier = $this->postJson('/api/admin/room-blocks', $this->block())->assertCreated()->json('data.id');
        $this->getJson('/api/admin/room-blocks')->assertJsonPath('data.0.id', $identifier);
        $this->getJson('/api/rooms/imeet/availability?date=2030-11-04')
            ->assertJsonPath('data.slots.1.available', false)
            ->assertJsonPath('data.slots.4.available', true);
        $this->getJson('/api/availability?date=2030-11-04')->assertJsonPath('data.0.slots.2.available', false);
        $this->getJson('/api/availability?date=2030-11-04')->assertDontSee('Internal maintenance');
        $this->getJson('/api/rooms/imeet/availability?date=2030-11-05')->assertJsonPath('data.slots.2.available', true);
        $this->postJson('/api/reservations', $this->booking($room))->assertUnprocessable();
        $this->postJson('/api/admin/reservations', $this->booking($room))->assertUnprocessable();
        $this->from('/admin')->post('/admin/reservations', $this->booking($room))
            ->assertRedirect('/admin')->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('reservations', 0);
        $this->deleteJson("/api/admin/room-blocks/{$identifier}")->assertNoContent();
        $this->postJson('/api/reservations', $this->booking($room))->assertCreated();
    }

    public function test_blocks_cannot_cover_existing_reservations_or_other_blocks(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        Reservation::factory()->for($room)->create(['reserved_date' => '2030-11-04', 'starts_at' => '10:00', 'ends_at' => '11:00', 'status' => 'confirmed']);
        $this->postJson('/api/admin/room-blocks', $this->block())->assertUnprocessable();
        $identifier = $this->postJson('/api/admin/room-blocks', [...$this->block(), 'date' => '2030-11-05'])->assertCreated()->json('data.id');
        $this->putJson("/api/admin/room-blocks/{$identifier}", $this->block())->assertUnprocessable();
        $this->postJson('/api/admin/room-blocks', [...$this->block(), 'date' => '2030-11-05'])->assertUnprocessable();
        $this->putJson("/api/admin/room-blocks/{$identifier}", [...$this->block(), 'date' => '2030-11-06'])->assertOk();
    }

    public function test_rescheduling_into_blocked_or_closed_time_is_rejected_without_changing_booking(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/admin/room-blocks', $this->block())->assertCreated();
        $reservation = Reservation::factory()->for($room)->create(['reserved_date' => '2030-11-05', 'starts_at' => '10:00', 'ends_at' => '11:00', 'status' => 'confirmed']);
        $this->putJson("/api/admin/reservations/{$reservation->id}", $this->booking($room))->assertUnprocessable();
        $this->assertSame('2030-11-05', $reservation->fresh()->reserved_date->format('Y-m-d'));
        $this->putJson('/api/admin/rooms/imeet/schedule', ['opening_hours' => array_fill(0, 7, null)])->assertOk();
        $this->putJson("/api/admin/reservations/{$reservation->id}", $this->booking($room, '2030-11-06'))->assertUnprocessable();
        $this->patchJson("/api/admin/reservations/{$reservation->id}/cancel")->assertOk();
    }

    public function test_block_updates_and_deletion_require_authentication(): void
    {
        $room = Room::factory()->create(['slug' => 'imeet']);
        $block = $room->blocks()->create(['blocked_date' => '2030-11-04', 'starts_at' => '10:00', 'ends_at' => '11:00']);
        $this->putJson("/api/admin/room-blocks/{$block->id}", $this->block())->assertUnauthorized();
        $this->deleteJson("/api/admin/room-blocks/{$block->id}")->assertUnauthorized();
        $this->assertDatabaseCount('room_blocks', 1);
    }

    private function block(): array
    {
        return ['room_id' => 'imeet', 'date' => '2030-11-04', 'start_time' => '10:00', 'end_time' => '11:00', 'notes' => 'Internal maintenance'];
    }

    private function booking(Room $room, string $date = '2030-11-04', string $start = '10:00'): array
    {
        return ['room_id' => $room->slug, 'date' => $date, 'start_time' => $start, 'first_name' => 'Ana', 'last_name' => 'Popescu', 'email' => 'ana@example.com', 'phone' => '060000000', 'status' => 'confirmed'];
    }
}
