<?php

namespace Database\Seeders;

use App\Models\PresenceLocation;
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
        // User::factory(10)->create();

        $location = PresenceLocation::factory()->create([
            'name' => 'Toko Utama',
            'latitude' => '-7.762250',
            'longitude' => '110.337500',
            'max_distance' => 50,
        ]);

        User::factory()->create([
            'presence_location_id' => $location->id,
            'username' => 'testuser',
            'name' => 'Test User',
            'role' => 'admin',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'presence_location_id' => $location->id,
            'username' => 'employee1',
            'name' => 'Employee One',
            'role' => 'employee',
            'email' => 'test2@example.com',
        ]);
    }
}
