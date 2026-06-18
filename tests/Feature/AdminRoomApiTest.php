<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
            'image_url' => 'https://example.test/imeet.jpg',
        ]);

        $this->getJson('/api/admin/rooms')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'imeet')
            ->assertJsonPath('data.0.business_id', 'chisinau')
            ->assertJsonPath('data.0.location', 'iHUB Chisinau')
            ->assertJsonPath('data.0.amenities.0', 'TV')
            ->assertJsonPath('data.0.accent', '#74bd45')
            ->assertJsonPath('data.0.image_url', 'https://example.test/imeet.jpg');
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
            'image_url' => 'https://example.test/podcast.jpg',
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', 'podcast-studio')
            ->assertJsonPath('data.name', 'Podcast Studio')
            ->assertJsonPath('data.business_id', 'yellow')
            ->assertJsonPath('data.image_url', 'https://example.test/podcast.jpg');

        $this->assertDatabaseHas('rooms', [
            'slug' => 'podcast-studio',
            'name' => 'Podcast Studio',
            'capacity' => 4,
            'business_id' => 'yellow',
            'image_url' => 'https://example.test/podcast.jpg',
        ]);
    }

    public function test_admin_can_update_a_room_image(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'image_url' => 'https://example.test/old.jpg',
        ]);

        $this->putJson('/api/admin/rooms/imeet', [
            'image_url' => 'https://example.test/new.jpg',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'imeet')
            ->assertJsonPath('data.image_url', 'https://example.test/new.jpg');

        $this->assertDatabaseHas('rooms', [
            'slug' => 'imeet',
            'image_url' => 'https://example.test/new.jpg',
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
}
