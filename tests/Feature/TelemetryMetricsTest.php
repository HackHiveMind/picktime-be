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
}
