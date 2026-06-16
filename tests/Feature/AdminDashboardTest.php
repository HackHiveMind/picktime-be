<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_reservations_without_loading_placeholder(): void
    {
        $room = Room::factory()->create([
            'name' => 'iMEET Room',
        ]);

        Reservation::factory()->for($room)->create([
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'reserved_date' => '2026-07-04',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Ana Popescu')
            ->assertSee('iMEET Room')
            ->assertSee('2026-07-04')
            ->assertDontSee('Loading reservations...');
    }

    public function test_admin_dashboard_redirects_to_configured_frontend_admin_url(): void
    {
        config(['app.admin_dashboard_url' => 'https://pictime-ihub-booking-fe.vercel.app/admin']);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertRedirect('https://pictime-ihub-booking-fe.vercel.app/admin');
    }

    public function test_admin_dashboard_shows_create_and_delete_controls(): void
    {
        $room = Room::factory()->create([
            'slug' => 'imeet',
            'name' => 'iMEET Room',
        ]);
        $reservation = Reservation::factory()->for($room)->create([
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Create reservation')
            ->assertSee('name="room_id"', false)
            ->assertSee('value="imeet"', false)
            ->assertSee("action=\"http://localhost:8000/admin/reservations/{$reservation->id}\"", false)
            ->assertSee('Delete');
    }

    public function test_admin_can_create_reservation_from_dashboard(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'name' => 'iMEET Room',
        ]);

        $this->actingAs(User::factory()->create())
            ->post('/admin/reservations', [
                'room_id' => 'imeet',
                'date' => '2026-07-04',
                'start_time' => '13:00',
                'end_time' => '15:00',
                'first_name' => 'Maria',
                'last_name' => 'Ionescu',
                'email' => 'maria@example.com',
                'phone' => '060000000',
                'status' => 'confirmed',
                'notes' => 'Created from dashboard',
            ])
            ->assertRedirect('/admin')
            ->assertSessionHas('status', 'Reservation created.');

        $this->assertDatabaseHas('reservations', [
            'first_name' => 'Maria',
            'last_name' => 'Ionescu',
            'email' => 'maria@example.com',
            'starts_at' => '13:00',
            'ends_at' => '15:00',
            'status' => 'confirmed',
        ]);
    }

    public function test_admin_can_delete_reservation_from_dashboard(): void
    {
        $reservation = Reservation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/admin/reservations/{$reservation->id}")
            ->assertRedirect('/admin')
            ->assertSessionHas('status', 'Reservation deleted.');

        $this->assertDatabaseMissing('reservations', [
            'id' => $reservation->id,
        ]);
    }

    public function test_admin_dashboard_reservation_actions_require_authentication(): void
    {
        Auth::logout();

        $reservation = Reservation::factory()->create();

        $this->post('/admin/reservations', [])
            ->assertRedirect('/admin/login');

        $this->delete("/admin/reservations/{$reservation->id}")
            ->assertRedirect('/admin/login');
    }
}
