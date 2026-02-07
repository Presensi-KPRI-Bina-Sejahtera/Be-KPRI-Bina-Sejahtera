<?php

namespace App\Services\Exports;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceExportService
{
    public function __construct(
        private readonly OpenSpoutXlsxExporter $xlsxExporter,
    ) {
    }

    /**
     * @param array{search?:string,start_date?:string,end_date?:string,user_id?:int} $filters
     */
    public function exportAttendanceToXlsx(array $filters): StreamedResponse
    {
        $search = $filters['search'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? today()->toDateString();

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
            $namaUser= Attendance::query()
                ->where('user_id', $userId)
                ->with('user:id,name,username')
                ->first()
                ?->user
                ?->name;
        }

        $baseQuery->whereBetween('date', [$startDate, $endDate]);

        $attendances = (clone $baseQuery)
            ->with(['user:id,name,username'])
            ->orderBy('date', 'desc')
            ->orderBy('user_id', 'asc')
            ->get();

        $fileName = 'presensi_' . ($search ? $search . '_' : '') . ($namaUser ?? 'all_users') . '_' . $startDate . '_sampai_' . $endDate . '.xlsx';

        $border = OpenSpoutStyleFactory::thinBorder();
        $headerStyle = OpenSpoutStyleFactory::headerStyle($border);
        $rowStyle = OpenSpoutStyleFactory::rowStyle($border);

        $headers = [
            'Nama',
            'Username',
            'Tanggal',
            'Jam Masuk',
            'Jam Pulang',
            'Total Jam',
            'Total Menit',
            'Durasi',
        ];

        $rows = $attendances->map(function ($attendance) {
            $jamMasuk = $attendance->jam_masuk;
            $jamPulang = $attendance->jam_pulang;

            $totalSeconds = null;
            if (!empty($jamMasuk) && !empty($jamPulang)) {
                $diff = strtotime($jamPulang) - strtotime($jamMasuk);
                $totalSeconds = $diff >= 0 ? $diff : null;
            }

            $totalHours = $totalSeconds !== null ? floor($totalSeconds / 3600) : null;
            $totalMinutes = $totalSeconds !== null ? floor(($totalSeconds % 3600) / 60) : null;
            $durationText = $totalSeconds !== null
                ? sprintf('%02d Jam %02d Menit', $totalHours, $totalMinutes)
                : null;

            $dateValue = $attendance->date;
            $dateText = method_exists($dateValue, 'format') ? $dateValue->format('Y-m-d') : (string) $dateValue;

            return [
                $attendance->user?->name ?? '',
                $attendance->user?->username ?? '',
                $dateText,
                $jamMasuk,
                $jamPulang,
                $totalHours,
                $totalMinutes,
                $durationText,
            ];
        });

        return $this->xlsxExporter->download($fileName, $headers, $rows, $headerStyle, $rowStyle, autoFitColumns: true);
    }
}
