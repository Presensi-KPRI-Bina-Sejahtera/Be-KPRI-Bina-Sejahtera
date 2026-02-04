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
        $this->call([
            PresenceLocationSeeder::class,
            UserSeeder::class,
            DepositSeeder::class,
            CashflowSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
