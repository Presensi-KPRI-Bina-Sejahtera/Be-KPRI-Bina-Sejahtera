<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    /**
     * Ambil daftar semua presensi.
     * GET /api/admin/attendance
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ], [
            'per_page.integer' => 'per_page harus berupa angka',
            'per_page.min' => 'per_page minimal 1',
            'search.string' => 'Search harus berupa teks',
            'start_date.date' => 'Tanggal mulai harus berupa tanggal yang valid',
            'end_date.date' => 'Tanggal selesai harus berupa tanggal yang valid',
            'end_date.after_or_equal' => 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai',
            'user_id.integer' => 'User harus berupa angka',
            'user_id.exists' => 'User tidak ditemukan',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = $validated['search'] ?? null;
        $userId = $validated['user_id'] ?? null;

        // Default: tanggal 1 bulan ini jika tidak ada start_date
        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? today()->toDateString();

        // Base query untuk filter (dipakai paginate + summary)
        $baseQuery = Attendance::query()
            ->select([
                'user_id',
                'date',
                DB::raw('MIN(CASE WHEN type = "datang" THEN time END) as jam_masuk'),
                DB::raw('MAX(CASE WHEN type = "pulang" THEN time END) as jam_pulang'),
            ])
            ->groupBy('user_id', 'date');

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            });
        }

        if ($userId) {
            $baseQuery->where('user_id', $userId);
        }

        if ($startDate) {
            $baseQuery->whereDate('date', '>=', $startDate);
        }
        if ($endDate) {
            $baseQuery->whereDate('date', '<=', $endDate);
        }

        $summarySubQuery = (clone $baseQuery)->toBase()->reorder();

        $summaryRow = DB::query()
            ->fromSub($summarySubQuery, 't')
            ->selectRaw('COUNT(DISTINCT user_id) as total_user')
            ->selectRaw('SUM(CASE WHEN jam_masuk IS NOT NULL AND jam_pulang IS NOT NULL THEN TIME_TO_SEC(TIMEDIFF(jam_pulang, jam_masuk)) ELSE 0 END) as total_seconds')
            ->first();

        $totalUser = (int) ($summaryRow->total_user ?? 0);
        $totalSeconds = (int) ($summaryRow->total_seconds ?? 0);
        $avgSecondsPerPerson = $totalUser > 0 ? ($totalSeconds / $totalUser) : 0;

        $summary = [
            'work_hours_avg' => round($avgSecondsPerPerson / 3600, 2),
        ];

        $query = (clone $baseQuery)
            ->with(['user:id,name,username,profile_image'])
            ->orderBy('date', 'desc')
            ->orderBy('user_id', 'asc');

        $attendances = $query->paginate($perPage);

        $attendanceData = collect($attendances->items())->map(function (Attendance $attendance) {
            $jamMasuk = $attendance->jam_masuk;
            $jamPulang = $attendance->jam_pulang;

            $totalSeconds = null;
            if (!empty($jamMasuk) && !empty($jamPulang)) {
                $diff = strtotime($jamPulang) - strtotime($jamMasuk);
                $totalSeconds = $diff >= 0 ? $diff : null;
            }

            $totalHours = $totalSeconds !== null ? round($totalSeconds / 3600, 2) : null;

            return [
                'user' => [
                    'id' => (int) $attendance->user->id,
                    'name' => $attendance->user->name,
                    'username' => $attendance->user->username,
                    'profile_image' => $attendance->user->getPhotoProfile(),
                ],
                'date' => $attendance->date->format('Y-m-d'),
                'jam_masuk' => $jamMasuk,
                'jam_pulang' => $jamPulang,
                'total_work_hours' => $totalHours,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar kehadiran berhasil diambil',
            'data' => [
                'current_page' => (int) $attendances->currentPage(),
                'last_page' => (int) $attendances->lastPage(),
                'per_page' => (int) $attendances->perPage(),
                'total' => (int) $attendances->total(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'summary' => $summary,
                'attendances' => $attendanceData,
            ],
        ]);
    }
}
