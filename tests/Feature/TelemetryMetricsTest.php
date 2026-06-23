<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TelemetryMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'telemetry.enabled' => true,
            'telemetry.metrics_token' => null,
        ]);

        Cache::clear();
    }

    public function test_metrics_endpoint_exposes_room_toggle_metrics(): void
    {
        $this->actingAs(User::factory()->create());

        Room::factory()->create([
            'slug' => 'imeet',
            'is_active' => true,
        ]);

        $this->patchJson('/api/admin/rooms/imeet', [
            'is_active' => false,
        ])->assertOk();

        $this->get('/api/metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8')
            ->assertSee('booking_room_toggle_total{operation="toggle_booking",room_id="imeet",service="admin_room",status="success"} 1', false)
            ->assertSee('booking_room_toggle_duration_seconds_bucket', false)
            ->assertSee('booking_room_toggle_duration_seconds_count{operation="toggle_booking",room_id="imeet",service="admin_room",status="success"} 1', false);
    }

    public function test_metrics_endpoint_can_require_bearer_token(): void
    {
        config(['telemetry.metrics_token' => 'secret-token']);

        $this->get('/api/metrics')->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer secret-token')
            ->get('/api/metrics')
            ->assertOk();
    }

    public function test_metrics_endpoint_exposes_admin_reservation_metrics(): void
    {
        $this->actingAs(User::factory()->create());

        Room::factory()->create(['slug' => 'imeet']);

        $response = $this->postJson('/api/admin/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
        ])->assertCreated();

        $reservationId = $response->json('data.id');

        $this->putJson("/api/admin/reservations/{$reservationId}", [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '14:00',
            'end_time' => '15:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
        ])->assertOk();

        $this->patchJson("/api/admin/reservations/{$reservationId}/cancel")->assertOk();
        $this->deleteJson("/api/admin/reservations/{$reservationId}")->assertNoContent();

        $this->get('/api/metrics')
            ->assertOk()
            ->assertSee('booking_reservation_create_total{operation="create",room_id="imeet",service="admin_reservation",status="success"} 1', false)
            ->assertSee('booking_reservation_update_total{operation="update",room_id="imeet",service="admin_reservation",status="success"} 1', false)
            ->assertSee('booking_reservation_cancel_total{operation="cancel",room_id="imeet",service="admin_reservation",status="success"} 1', false)
            ->assertSee('booking_reservation_delete_total{operation="delete",room_id="imeet",service="admin_reservation",status="success"} 1', false)
            ->assertSee('booking_reservation_create_duration_seconds_count{operation="create",room_id="imeet",service="admin_reservation",status="success"} 1', false);
    }

    public function test_metrics_endpoint_exposes_public_booking_metrics(): void
    {
        Room::factory()->create(['slug' => 'imeet']);

        $this->postJson('/api/reservations', [
            'room_id' => 'imeet',
            'date' => '2026-07-04',
            'start_time' => '09:00',
            'first_name' => 'Ana',
            'last_name' => 'Popescu',
            'email' => 'ana@example.com',
            'phone' => '069123456',
        ])->assertCreated();

        $this->get('/api/metrics')
            ->assertOk()
            ->assertSee('booking_reservation_create_total{operation="create",room_id="imeet",service="public_booking",status="success"} 1', false)
            ->assertSee('booking_reservation_create_duration_seconds_count{operation="create",room_id="imeet",service="public_booking",status="success"} 1', false);
    }
}
