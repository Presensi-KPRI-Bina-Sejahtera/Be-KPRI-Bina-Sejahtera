<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Cashflow;
use App\Models\Deposit;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Ambil data dashboard.
     * GET /api/dashboard
     */
    public function index(Request $request): Response
    {
        /**
         * Ringkasan pemasukan section
         */
        $lastMonth = now()->subMonth();

        $pemasukan = Cashflow::where('type', 'pemasukan')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('value');

        $pemasukanLastMonth = Cashflow::where('type', 'pemasukan')
            ->whereMonth('date', $lastMonth->month)
            ->whereYear('date', $lastMonth->year)
            ->sum('value');

        if ($pemasukanLastMonth > 0) {
            $pemasukanChange = round((($pemasukan - $pemasukanLastMonth) / $pemasukanLastMonth) * 100, 2);
        } else {
            $pemasukanChange = $pemasukan > 0 ? 100 : 0;
        }

        /**
         * Ringkasan pengeluaran section
         */
        $pengeluaran = Cashflow::where('type', 'pengeluaran')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('value');

        $pengeluaranLastMonth = Cashflow::where('type', 'pengeluaran')
            ->whereMonth('date', $lastMonth->month)
            ->whereYear('date', $lastMonth->year)
            ->sum('value');

        if ($pengeluaranLastMonth > 0) {
            $pengeluaranChange = round((($pengeluaran - $pengeluaranLastMonth) / $pengeluaranLastMonth) * 100, 2);
        } else {
            $pengeluaranChange = $pengeluaran > 0 ? 100 : 0;
        }

        /**
         * Ringkasan deposit section
         */
        $deposit = Deposit::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('value');
        $depositPerson = Deposit::selectRaw('COUNT(DISTINCT LOWER(for_name)) as total')
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->value('total');


        /**
         * Ringkasan jam kerja section
         */
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();
        $baseQuery = Attendance::query()
            ->select([
                'user_id',
                'date',
                DB::raw('MIN(CASE WHEN type = "datang" THEN time END) as jam_masuk'),
                DB::raw('MAX(CASE WHEN type = "pulang" THEN time END) as jam_pulang'),
            ])
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->groupBy('user_id', 'date');

        $summarySubQuery = (clone $baseQuery)->toBase()->reorder();
        $summaryRow = DB::query()
            ->fromSub($summarySubQuery, 't')
            ->selectRaw('COUNT(DISTINCT user_id) as total_user')
            ->selectRaw('SUM(CASE WHEN jam_masuk IS NOT NULL AND jam_pulang IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(jam_pulang, jam_masuk)) ELSE 0 END) as total_seconds')
            ->first();

        $totalUser = (int) ($summaryRow->total_user ?? 0);
        $totalSeconds = (int) ($summaryRow->total_seconds ?? 0);
        $avgSecondsPerPerson = $totalUser > 0 ? ($totalSeconds / $totalUser) : 0;
        $avgHours = round($avgSecondsPerPerson / 3600, 2);
        $avgHoursPerDay = round($avgSecondsPerPerson / 3600 / now()->daysInMonth, 2);
        $avgHoursInt = floor($avgHoursPerDay);
        $avgMinutesInt = floor(($avgHoursPerDay - $avgHoursInt) * 60);
        $avgText = $avgHoursInt.' Jam '.$avgMinutesInt.' Menit';

        // Calculate last month's average work hours per person per day
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();
        $lastMonthDays = now()->subMonth()->daysInMonth;
        $baseQueryLastMonth = Attendance::query()
            ->select([
                'user_id',
                'date',
                DB::raw('MIN(CASE WHEN type = "datang" THEN time END) as jam_masuk'),
                DB::raw('MAX(CASE WHEN type = "pulang" THEN time END) as jam_pulang'),
            ])
            ->whereDate('date', '>=', $lastMonthStart)
            ->whereDate('date', '<=', $lastMonthEnd)
            ->groupBy('user_id', 'date');

        $summarySubQueryLastMonth = (clone $baseQueryLastMonth)->toBase()->reorder();
        $summaryRowLastMonth = DB::query()
            ->fromSub($summarySubQueryLastMonth, 't')
            ->selectRaw('COUNT(DISTINCT user_id) as total_user')
            ->selectRaw('SUM(CASE WHEN jam_masuk IS NOT NULL AND jam_pulang IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(jam_pulang, jam_masuk)) ELSE 0 END) as total_seconds')
            ->first();

        $totalUserLastMonth = (int) ($summaryRowLastMonth->total_user ?? 0);
        $totalSecondsLastMonth = (int) ($summaryRowLastMonth->total_seconds ?? 0);
        $avgSecondsPerPersonLastMonth = $totalUserLastMonth > 0 ? ($totalSecondsLastMonth / $totalUserLastMonth) : 0;
        $avgHoursPerDayLastMonth = $lastMonthDays > 0 ? round($avgSecondsPerPersonLastMonth / 3600 / $lastMonthDays, 2) : 0;

        // Calculate percentage change
        if ($avgHoursPerDayLastMonth > 0) {
            $workHoursChange = round((($avgHoursPerDay - $avgHoursPerDayLastMonth) / $avgHoursPerDayLastMonth) * 100, 2);
        } else {
            $workHoursChange = $avgHoursPerDay > 0 ? 100 : 0;
        }

        /**
         * Grafik work hours and pemasukan pengeluaran section
         */
        $grafikLabels = [];
        $workHourData = [];
        $pemasukandata = [];
        $pengeluarandata = [];
        $hariIndo = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $dayName = $hariIndo[date('l', strtotime($date))];
            $workHourLabels[] = $dayName;
            $dailySummarySubQuery = (clone $baseQuery)
                ->toBase()
                ->reorder()
                ->whereDate('date', $date);
            $dailySummaryRow = DB::query()
                ->fromSub($dailySummarySubQuery, 't')
                ->selectRaw('COUNT(DISTINCT user_id) as total_user')
                ->selectRaw('SUM(CASE WHEN jam_masuk IS NOT NULL AND jam_pulang IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(jam_pulang, jam_masuk)) ELSE 0 END) as total_seconds')
                ->first();

            $totalUser = (int) ($dailySummaryRow->total_user ?? 0);
            $totalSeconds = (int) ($dailySummaryRow->total_seconds ?? 0);
            $avgSecondsPerPerson = $totalUser > 0 ? ($totalSeconds / $totalUser) : 0;
            $avgHoursPerDay = round($avgSecondsPerPerson / 3600, 2);
            $workHourData[] = $avgHoursPerDay;

            // Pemasukan dan pengeluaran
            $dailyPemasukan = Cashflow::where('type', 'pemasukan')
                ->whereDate('date', $date)
                ->sum('value');
            $dailyPengeluaran = Cashflow::where('type', 'pengeluaran')
                ->whereDate('date', $date)
                ->sum('value');
            $pemasukandata[] = (int) $dailyPemasukan;
            $pengeluarandata[] = (int) $dailyPengeluaran;
        }

        /**
         * Data pulang laporan setoran section
         */
        $attendances = Attendance::where('type', 'pulang')
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->limit(10)
            ->get();
        $pulang_laporan_setoran = [];
        foreach ($attendances as $attendance) {
            $user = $attendance->user;
            $date = $attendance->date;
            $time = $attendance->time;
            $pemasukanTabel = Cashflow::where('type', 'pemasukan')
                ->where('user_id', $attendance->user_id)
                ->whereDate('date', $date)
                ->sum('value');
            $pengeluaranTabel = Cashflow::where('type', 'pengeluaran')
                ->where('user_id', $attendance->user_id)
                ->whereDate('date', $date)
                ->sum('value');
            $depositTabel = Deposit::where('user_id', $attendance->user_id)
                ->whereDate('date', $date)
                ->sum('value');
            $pulang_laporan_setoran[] = [
                'user' => [
                    'id' => $user ? $user->id : $attendance->user_id,
                    'name' => $user ? $user->name : '',
                    'username' => $user ? $user->username : '',
                    'profile_image' => $user ? $user->profile_image : null,
                ],
                'date' => $date->format('Y-m-d'),
                'time' => $time->format('H:i:s'),
                'pemasukan' => (int) $pemasukanTabel,
                'pengeluaran' => (int) $pengeluaranTabel,
                'deposit' => (int) $depositTabel,
            ];
        }
        /**
         * Pemasukan dan pengeluaran
         */

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data dashboard',
            'data' => [
                'statistik' => [
                    'pemasukan' => [
                        'total' => (int) $pemasukan,
                        'change' => (float) $pemasukanChange,
                    ],
                    'pengeluaran' => [
                        'total' => (int) $pengeluaran,
                        'change' => (float) $pengeluaranChange,
                    ],
                    'deposit' => [
                        'total' => (int) $deposit,
                        'person' => (int) $depositPerson,
                    ],
                    'work_hours' => [
                        'average' => [
                            'hours' => $avgHoursInt,
                            'minutes' => $avgMinutesInt,
                            'text' => $avgText,
                        ],
                        'change' => (float) $workHoursChange,
                    ],
                ],
                'grafik' => [
                    'labels' => $grafikLabels,
                    'work_hours' => [
                        'data' => $workHourData,
                    ],
                    'cashflows' => [
                        'pemasukan' => $pemasukandata,
                        'pengeluaran' => $pengeluarandata,
                    ],
                ],
                'pulang_laporan_setoran' => $pulang_laporan_setoran
            ],
        ], 200);
    }
}
