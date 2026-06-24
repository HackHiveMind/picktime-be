<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use App\Services\Telemetry\MetricsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminRoomApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_admin_rooms_require_authentication(): void
    {
        Auth::logout();

        $this->getJson('/api/admin/rooms')
            ->assertUnauthorized();
    }

    public function test_admin_can_list_rooms_with_business_metadata(): void
    {
        Room::factory()->create([
            'name' => 'iMEET Room',
            'slug' => 'imeet',
            'capacity' => 8,
            'business_id' => 'chisinau',
            'location' => 'iHUB Chisinau',
            'amenities' => ['TV'],
            'accent' => '#74bd45',
        ]);

        $this->getJson('/api/admin/rooms')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'imeet')
            ->assertJsonPath('data.0.business_id', 'chisinau')
            ->assertJsonPath('data.0.location', 'iHUB Chisinau')
            ->assertJsonPath('data.0.amenities.0', 'TV')
            ->assertJsonPath('data.0.accent', '#74bd45');
    }

    public function test_admin_can_create_a_room(): void
    {
        $this->postJson('/api/admin/rooms', [
            'name' => 'Podcast Studio',
            'capacity' => 4,
            'business_id' => 'yellow',
            'location' => 'iHUB Yellow',
            'amenities' => ['Mic'],
            'accent' => '#f7de05',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', 'podcast-studio')
            ->assertJsonPath('data.name', 'Podcast Studio')
            ->assertJsonPath('data.business_id', 'yellow');

        $this->assertDatabaseHas('rooms', [
            'slug' => 'podcast-studio',
            'name' => 'Podcast Studio',
            'capacity' => 4,
            'business_id' => 'yellow',
        ]);
    }

    public function test_admin_can_move_a_room_to_yellow_business(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'business_id' => 'chisinau',
            'location' => 'iHUB Chisinau',
        ]);

        $this->putJson('/api/admin/rooms/imeet', [
            'business_id' => 'yellow',
            'location' => 'iHUB Yellow',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'imeet')
            ->assertJsonPath('data.business_id', 'yellow')
            ->assertJsonPath('data.location', 'iHUB Yellow');

        $this->assertDatabaseHas('rooms', [
            'slug' => 'imeet',
            'business_id' => 'yellow',
            'location' => 'iHUB Yellow',
        ]);
    }

    public function test_admin_cannot_use_removed_businesses(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'business_id' => 'chisinau',
            'location' => 'iHUB Chisinau',
        ]);

        $this->putJson('/api/admin/rooms/imeet', [
            'business_id' => 'yellow-conference',
            'location' => 'iHUB Yellow Conference',
        ])->assertUnprocessable();

        $this->patchJson('/api/admin/rooms/imeet', [
            'business_id' => 'wfp-conference',
            'location' => 'iHUB - WFP Conference',
        ])->assertUnprocessable();
    }

    public function test_admin_can_patch_a_room_to_another_business(): void
    {
        Room::factory()->create([
            'slug' => 'loft',
            'business_id' => 'chisinau',
            'location' => 'iHUB Chisinau',
        ]);

        $this->patchJson('/api/admin/rooms/loft', [
            'business_id' => 'yellow',
            'location' => 'iHUB Yellow',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'loft')
            ->assertJsonPath('data.business_id', 'yellow')
            ->assertJsonPath('data.location', 'iHUB Yellow');

        $this->assertDatabaseHas('rooms', [
            'slug' => 'loft',
            'business_id' => 'yellow',
            'location' => 'iHUB Yellow',
        ]);
    }

    public function test_admin_can_toggle_room_booking_under_one_second(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'is_active' => true,
        ]);

        $startedAt = microtime(true);

        $this->patchJson('/api/admin/rooms/imeet', [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'imeet')
            ->assertJsonPath('data.is_active', false);

        $this->assertLessThan(
            1.0,
            microtime(true) - $startedAt,
            'Room booking toggle should complete under the 1 second KPI.',
        );
        $this->assertDatabaseHas('rooms', [
            'slug' => 'imeet',
            'is_active' => false,
        ]);
    }

    public function test_room_booking_toggle_records_telemetry(): void
    {
        $metrics = new class implements MetricsRecorder
        {
            /** @var array<int, array{metric: string, labels: array<string, string>}> */
            public array $increments = [];

            /** @var array<int, array{metric: string, value: float, labels: array<string, string>}> */
            public array $observations = [];

            public function increment(string $metric, array $labels = []): void
            {
                $this->increments[] = compact('metric', 'labels');
            }

            public function observe(string $metric, float $value, array $labels = []): void
            {
                $this->observations[] = compact('metric', 'value', 'labels');
            }
        };

        $this->app->instance(MetricsRecorder::class, $metrics);

        Room::factory()->create([
            'slug' => 'imeet',
            'is_active' => true,
        ]);

        $this->patchJson('/api/admin/rooms/imeet', [
            'is_active' => false,
        ])->assertOk();

        $this->assertSame('booking_room_toggle_total', $metrics->increments[0]['metric'] ?? null);
        $this->assertSame('booking_room_toggle_duration_seconds', $metrics->observations[0]['metric'] ?? null);
        $this->assertSame([
            'service' => 'admin_room',
            'operation' => 'toggle_booking',
            'room_id' => 'imeet',
            'status' => 'success',
        ], $metrics->increments[0]['labels'] ?? []);
        $this->assertGreaterThanOrEqual(0, $metrics->observations[0]['value'] ?? -1);
    }

    public function test_room_booking_toggle_skips_write_when_state_is_unchanged(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'is_active' => false,
        ]);

        DB::enableQueryLog();

        $this->patchJson('/api/admin/rooms/imeet', [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'imeet')
            ->assertJsonPath('data.is_active', false);

        $updateQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_starts_with(strtolower($query['query']), 'update "rooms"'))
            ->count();

        $this->assertSame(0, $updateQueries);
    }

    public function test_room_booking_toggle_includes_server_timing_diagnostics(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'is_active' => true,
        ]);

        $response = $this->patchJson('/api/admin/rooms/imeet', [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertHeader('Server-Timing');

        $serverTiming = $response->headers->get('Server-Timing', '');

        $this->assertStringContainsString('total;dur=', $serverTiming);
        $this->assertStringContainsString('admin_auth;dur=', $serverTiming);
        $this->assertStringContainsString('toggle_service;dur=', $serverTiming);
        $this->assertStringContainsString('toggle_db;dur=', $serverTiming);
    }
}
