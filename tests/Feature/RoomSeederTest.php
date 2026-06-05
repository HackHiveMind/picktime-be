<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_ihub_rooms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('rooms', [
            'slug' => 'imeet',
            'name' => 'iMEET Room',
            'capacity' => 8,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('rooms', [
            'slug' => 'loft',
            'name' => 'Loft Room',
            'capacity' => 8,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('rooms', [
            'slug' => 'green-conference',
            'name' => 'Green Conference Room',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('rooms', [
            'slug' => 'yellow-conference',
            'name' => 'Yellow Conference Room',
            'capacity' => 30,
            'is_active' => true,
        ]);
    }
}

