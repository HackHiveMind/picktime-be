<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $rooms = [
            [
                'slug' => 'imeet',
                'name' => 'iMEET Room',
                'capacity' => 8,
            ],
            [
                'slug' => 'loft',
                'name' => 'Loft Room',
                'capacity' => 8,
            ],
            [
                'slug' => 'green-conference',
                'name' => 'Green Conference Room',
                'capacity' => 20,
            ],
            [
                'slug' => 'yellow-conference',
                'name' => 'Yellow Conference Room',
                'capacity' => 30,
            ],
        ];

        foreach ($rooms as $room) {
            Room::query()->updateOrCreate(
                ['slug' => $room['slug']],
                [
                    'name' => $room['name'],
                    'capacity' => $room['capacity'],
                    'is_active' => true,
                ],
            );
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
