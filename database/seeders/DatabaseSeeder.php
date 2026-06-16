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
                'business_id' => 'chisinau',
                'location' => 'iHUB Chisinau',
                'amenities' => ['TV', 'Whiteboard', 'Video call', 'Cafea/ceai'],
                'accent' => '#74bd45',
            ],
            [
                'slug' => 'loft',
                'name' => 'Loft Room',
                'capacity' => 8,
                'business_id' => 'yellow',
                'location' => 'iHUB Yellow',
                'amenities' => ['Monitor', 'Whiteboard', 'Internet rapid', 'Bucatarie'],
                'accent' => '#f7de05',
            ],
            [
                'slug' => 'green-conference',
                'name' => 'Green Conference Room',
                'capacity' => 20,
                'business_id' => 'chisinau',
                'location' => 'iHUB Chisinau',
                'amenities' => ['Ecran 100 inch', 'Sistem audio/video', 'Flipchart', 'Suport IT'],
                'accent' => '#74bd45',
            ],
            [
                'slug' => 'yellow-conference',
                'name' => 'Yellow Conference Room',
                'capacity' => 30,
                'business_id' => 'yellow',
                'location' => 'iHUB Yellow',
                'amenities' => ['Proiector', 'Internet', 'Flipchart', 'Suport IT'],
                'accent' => '#f7de05',
            ],
        ];

        foreach ($rooms as $room) {
            Room::query()->updateOrCreate(
                ['slug' => $room['slug']],
                [
                    'name' => $room['name'],
                    'capacity' => $room['capacity'],
                    'business_id' => $room['business_id'],
                    'location' => $room['location'],
                    'amenities' => $room['amenities'],
                    'accent' => $room['accent'],
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
