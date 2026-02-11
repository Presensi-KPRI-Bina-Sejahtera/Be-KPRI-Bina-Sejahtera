<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'employee')->get();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }

        $now = now();

        foreach ($users as $user) {
            // Seed 15 hari terakhir
            $usedDates = [];
            foreach (range(0, 14) as $daysAgo) {
                // Generate a unique date for this user
                do {
                    $date = $now->copy()->subDays($daysAgo)->toDateString();
                } while (in_array($date, $usedDates));

                $usedDates[] = $date;

                // Jam datang: 07:00 - 09:30
                $datangHour = rand(7, 9);
                $datangMinute = $datangHour === 9 ? rand(0, 30) : rand(0, 59);
                $datangTime = sprintf('%02d:%02d:%02d', $datangHour, $datangMinute, rand(0, 59));

                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'type' => 'datang',
                    'time' => $datangTime,
                    'distance' => rand(0, 50),
                ]);

                // Jam pulang: 16:00 - 18:30
                $pulangHour = rand(16, 18);
                $pulangMinute = $pulangHour === 18 ? rand(0, 30) : rand(0, 59);
                $pulangTime = sprintf('%02d:%02d:%02d', $pulangHour, $pulangMinute, rand(0, 59));

                Attendance::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'type' => 'pulang',
                    'time' => $pulangTime,
                    'distance' => rand(0, 50),
                ]);
            }
        }
    }
}
