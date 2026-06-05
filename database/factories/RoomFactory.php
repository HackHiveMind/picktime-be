<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => str($name)->title()->toString(),
            'slug' => str($name)->slug()->toString(),
            'capacity' => fake()->numberBetween(4, 24),
            'is_active' => true,
        ];
    }
}
