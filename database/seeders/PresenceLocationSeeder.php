<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PresenceLocation;

class PresenceLocationSeeder extends Seeder
{
    public function run(): void
    {
        PresenceLocation::create([
            'name' => 'Toko Utama',
            'address' => 'Alamat belum diisi',
            'latitude' => '-7.762250',
            'longitude' => '110.337500',
            'max_distance' => 50,
        ]);
    }
}
