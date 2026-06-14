<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoomApiTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_can_move_a_room_to_another_business(): void
    {
        Room::factory()->create([
            'slug' => 'imeet',
            'business_id' => 'chisinau',
            'location' => 'iHUB Chisinau',
        ]);

        $this->putJson('/api/admin/rooms/imeet', [
            'business_id' => 'yellow-conference',
            'location' => 'iHUB Yellow Conference',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', 'imeet')
            ->assertJsonPath('data.business_id', 'yellow-conference')
            ->assertJsonPath('data.location', 'iHUB Yellow Conference');

        $this->assertDatabaseHas('rooms', [
            'slug' => 'imeet',
            'business_id' => 'yellow-conference',
            'location' => 'iHUB Yellow Conference',
        ]);
    }
}
