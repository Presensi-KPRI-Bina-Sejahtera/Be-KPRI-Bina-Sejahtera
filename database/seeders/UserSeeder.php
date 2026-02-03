<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\PresenceLocation;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $location = PresenceLocation::first();
        if (!$location) {
            $this->command->warn('No presence location found. Please seed presence locations first.');
            return;
        }

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'testuser',
            'name' => 'Test User',
            'role' => 'admin',
            'email' => 'test@example.com',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'employee1',
            'name' => 'Employee One',
            'role' => 'employee',
            'email' => 'test2@example.com',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'fauzan1',
            'name' => 'Fauzan Trisuladana',
            'role' => 'admin',
            'email' => 'fauzantrisuladana@gmail.com',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'fauzan2',
            'name' => 'Muhammad Fauzan Putra Trisuladana',
            'role' => 'admin',
            'email' => 'muhammadfauzanputratrisuladana@mail.ugm.ac.id',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'afif1',
            'name' => 'Abdullah Afif',
            'role' => 'admin',
            'email' => 'abdullahafifh@gmail.com',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'afif2',
            'name' => 'Afef Space',
            'role' => 'admin',
            'email' => 'afefspace@gmail.com',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'afif3',
            'name' => 'Catetan Saja',
            'role' => 'employee',
            'email' => 'catetansaja@gmail.com',
        ]);

        User::create([
            'presence_location_id' => $location->id,
            'username' => 'rio1',
            'name' => 'Prihastomo Budisatrio',
            'role' => 'admin',
            'email' => 'prihastomobudisatrio2005@mail.ugm.ac.id',
        ]);
    }
}
