<?php

namespace App\Services\Exports;

use App\Models\Deposit;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepositExportService
{
    public function __construct(
        private readonly OpenSpoutXlsxExporter $xlsxExporter,
    ) {
    }

    /**
     * @param array{search?:string,start_date?:string,end_date?:string,type?:string,status?:string} $filters
     */
    public function exportDepositToXlsx(array $filters): StreamedResponse
    {
        $search = $filters['search'] ?? null;
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;

        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? today()->toDateString();

        $baseQuery = Deposit::query();

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('for_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($type)) {
            $baseQuery->where('type', $type);
        }

        if (!empty($status)) {
            if ($status === 'pending') {
                $baseQuery->whereNull('verified_key');
            } elseif ($status === 'verified') {
                $baseQuery->whereNotNull('verified_key');
            }
        }

        $baseQuery->whereBetween('date', [$startDate, $endDate]);

        $deposits = (clone $baseQuery)
            ->with(['user:id,name,username'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $safeSearch = $search ? (Str::slug($search, '_') ?: null) : null;
        $safeType = Str::slug($type ?: 'setoran_angsuran', '_') ?: 'setoran_angsuran';
        $safeStatus = Str::slug($status ?: 'terkonfirmasi_dan_belum_terkonfirmasi', '_') ?: 'terkonfirmasi_dan_belum_terkonfirmasi';
        $fileName = 'deposit_' . ($safeSearch ? $safeSearch . '_' : '') . $safeType . '_' . $safeStatus . '_' . $startDate . '_sampai_' . $endDate . '.xlsx';

        $border = OpenSpoutStyleFactory::thinBorder();
        $headerStyle = OpenSpoutStyleFactory::headerStyle($border);
        $rowStyle = OpenSpoutStyleFactory::rowStyle($border);

        $headers = [
            'Nama User',
            'Username',
            'Atas Nama',
            'Tipe',
            'Tanggal',
            'Value',
            'Status',
            'Verified Key',
        ];

        $rows = $deposits->map(function (Deposit $deposit) {
            $dateValue = $deposit->date;
            $dateText = method_exists($dateValue, 'format') ? $dateValue->format('Y-m-d') : (string) $dateValue;
            $isVerified = !is_null($deposit->verified_key);

            return [
                $deposit->user?->name ?? '',
                $deposit->user?->username ?? '',
                $deposit->for_name,
                $deposit->type,
                $dateText,
                (int) $deposit->value,
                $isVerified ? 'verified' : 'pending',
                $deposit->verified_key,
            ];
        });

        return $this->xlsxExporter->download($fileName, $headers, $rows, $headerStyle, $rowStyle, autoFitColumns: true);
    }
}
