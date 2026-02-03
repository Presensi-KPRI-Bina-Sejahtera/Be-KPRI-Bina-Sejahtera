<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cashflow;
use App\Models\User;
use Illuminate\Support\Arr;

class CashflowSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }

        $types = ['pemasukan', 'pengeluaran'];
        $now = now();

        foreach ($users as $user) {
            foreach (range(1, 5) as $i) {
                $type = Arr::random($types);
                Cashflow::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'date' => $now->copy()->subDays(rand(0, 30)),
                    'value' => rand(10000, 1000000),
                ]);
            }
        }
    }
}
