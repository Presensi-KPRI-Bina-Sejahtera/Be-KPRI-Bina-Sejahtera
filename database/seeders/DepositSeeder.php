<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class DepositSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }

        $types = ['simpanan', 'angsuran'];
        $now = now();

        foreach ($users as $user) {
            foreach (range(1, 5) as $i) {
                $type = Arr::random($types);
                $isVerified = rand(0, 1) === 1;
                Deposit::create([
                    'user_id' => $user->id,
                    'for_name' => $user->name,
                    'type' => $type,
                    'date' => $now->copy()->subDays(rand(0, 30)),
                    'value' => rand(10000, 1000000),
                    'verified_key' => $isVerified ? Str::uuid() : null,
                ]);
            }
        }
    }
}
