<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\PresenceLocation;
use Illuminate\Support\Facades\DB;
use App\Services\Exports\AttendanceExportService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $baseQuery->whereBetween('date', [$startDate, $endDate]);

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
            'work_hours_avg_perperson' => round($avgSecondsPerPerson / 3600, 2),
            'work_hours_avg_perperson_perday' => round($avgSecondsPerPerson / 3600 / ((strtotime($endDate) - strtotime($startDate)) / 86400 + 1), 2),
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

            $totalHours = $totalSeconds !== null ? floor($totalSeconds / 3600) : null;
            $totalMinutes = $totalSeconds !== null ? floor(($totalSeconds % 3600) / 60) : null;

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
                'total_work_minutes' => $totalMinutes,
                'work_duration_text' => $totalSeconds !== null ? sprintf('%02d Jam %02d Menit', $totalHours, $totalMinutes) : null,
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
        ], 200);
    }

    /**
     * Export daftar presensi (admin) ke Excel.
     * GET /api/admin/attendance/export-excel
     */
    public function exportExcel(Request $request, AttendanceExportService $exportService): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ], [
            'search.string' => 'Search harus berupa teks',
            'start_date.date' => 'Tanggal mulai harus berupa tanggal yang valid',
            'end_date.date' => 'Tanggal selesai harus berupa tanggal yang valid',
            'end_date.after_or_equal' => 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai',
            'user_id.integer' => 'User harus berupa angka',
            'user_id.exists' => 'User tidak ditemukan',
        ]);

        return $exportService->exportAttendanceToXlsx($validated);
    }

    /**
     * Ambil data presensi hari ini untuk user yang sedang login.
     * GET /api/attendance/today
     */
    public function today(Request $request): Response
    {
        $user = $request->user();
        $today = today()->toDateString();

        $attendance = Attendance::query()
            ->select([
                'user_id',
                'date',
                DB::raw('MIN(CASE WHEN type = "datang" THEN time END) as jam_masuk'),
                DB::raw('MAX(CASE WHEN type = "pulang" THEN time END) as jam_pulang'),
            ])
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->groupBy('user_id', 'date')
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => 'success',
                'message' => 'Tidak ada data presensi untuk hari ini',
                'data' => null,
            ], 200);
        }

        $jamMasuk = $attendance->jam_masuk;
        $jamPulang = $attendance->jam_pulang;

        $totalSeconds = null;
        if (!empty($jamMasuk) && !empty($jamPulang)) {
            $diff = strtotime($jamPulang) - strtotime($jamMasuk);
            $totalSeconds = $diff >= 0 ? $diff : null;
        }


        $totalHours = $totalSeconds !== null ? floor($totalSeconds / 3600) : null;
        $totalMinutes = $totalSeconds !== null ? floor(($totalSeconds % 3600) / 60) : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Data presensi untuk hari ini berhasil diambil',
            'data' => [
                'jam_masuk' => $jamMasuk,
                'sudah_masuk' => !is_null($jamMasuk),
                'jam_pulang' => $jamPulang,
                'sudah_pulang' => !is_null($jamPulang),
                'total_work_hours' => $totalHours,
                'total_work_minutes' => $totalMinutes,
                'work_duration_text' => $totalSeconds !== null ? sprintf('%02d Jam %02d Menit', $totalHours, $totalMinutes) : null,
            ],
        ], 200);
    }

    /**
     * Simpan data presensi masuk untuk user yang sedang login.
     * POST /api/attendance/check-in
     */
    public function checkIn(Request $request): Response
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ], [
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
        ]);
        $user = $request->user();
        $today = today()->toDateString();

        // Cek apakah sudah ada data presensi untuk hari ini
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->where('type', 'datang')
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah melakukan presensi masuk hari ini',
            ], 400);
        }

        $distance = PresenceLocation::calculateDistance(
            $user->presenceLocation,
            $validated['latitude'],
            $validated['longitude'],
        );

        if ($distance >= $user->presenceLocation->max_distance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda berada di luar jangkauan lokasi presensi yang diizinkan',
                'data' => [
                    'distance' => $distance,
                    'max_distance' => $user->presenceLocation->max_distance,
                ],
            ], 400);
        }

        // Simpan data presensi masuk
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $today,
            'type' => 'datang',
            'time' => now()->format('H:i:s'),
            'distance' => $distance,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Presensi masuk berhasil disimpan',
            'data' => [
                'time' => $attendance->time->format('H:i:s'),
                'distance' => $attendance->distance,
            ],
        ], 201);
    }

    /**
     * Simpan data presensi pulang untuk user yang sedang login.
     * POST /api/attendance/check-out
     */
    public function checkOut(Request $request): Response
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ], [
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.numeric' => 'Latitude harus berupa angka',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.numeric' => 'Longitude harus berupa angka',
        ]);
        $user = $request->user();
        $today = today()->toDateString();


        $existingCheckIn = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->where('type', 'datang')
            ->first();

        if (!$existingCheckIn) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda belum melakukan presensi masuk hari ini',
            ], 400);
        }

        $existingCheckOut = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->where('type', 'pulang')
            ->first();
        if ($existingCheckOut) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah melakukan presensi pulang hari ini',
            ], 400);
        }
        $distance = PresenceLocation::calculateDistance(
            $user->presenceLocation,
            $validated['latitude'],
            $validated['longitude'],
        );
        if ($distance >= $user->presenceLocation->max_distance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda berada di luar jangkauan lokasi presensi yang diizinkan',
                'data' => [
                    'distance' => $distance,
                    'max_distance' => $user->presenceLocation->max_distance,
                ],
            ], 400);
        }
        // Simpan data presensi pulang
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $today,
            'type' => 'pulang',
            'time' => now()->format('H:i:s'),
            'distance' => $distance,
        ]);
        return response()->json([
            'status' => 'success',
            'message' => 'Presensi pulang berhasil disimpan',
            'data' => [
                'time' => $attendance->time->format('H:i:s'),
                'distance' => $attendance->distance,
            ],
        ], 201);
    }
}
