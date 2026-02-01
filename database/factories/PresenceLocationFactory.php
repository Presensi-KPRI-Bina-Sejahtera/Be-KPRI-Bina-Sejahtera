<?php

namespace Database\Factories;

use App\Models\PresenceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PresenceLocation>
 */
class PresenceLocationFactory extends Factory
{
    protected $model = PresenceLocation::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'latitude' => (string) fake()->latitude(),
            'longitude' => (string) fake()->longitude(),
            'max_distance' => 50,
        ];
    }
}
