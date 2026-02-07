<?php

namespace App\Services\Exports;

use App\Models\Cashflow;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowExportService
{
    public function __construct(
        private readonly OpenSpoutXlsxExporter $xlsxExporter,
    ) {
    }

    /**
     * @param array{search?:string,start_date?:string,end_date?:string,type?:string} $filters
     */
    public function exportCashflowToXlsx(array $filters): StreamedResponse
    {
        $search = $filters['search'] ?? null;
        $type = $filters['type'] ?? null;

        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? today()->toDateString();

        $baseQuery = Cashflow::query();

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            });
        }

        if (!empty($type)) {
            $baseQuery->where('type', $type);
        }

        $baseQuery->whereBetween('date', [$startDate, $endDate]);

        $cashflows = (clone $baseQuery)
            ->with(['user:id,name,username'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $safeType = $type ?: 'pemasukan_pengeluaran';
        $safeSearch = $search ? Str::slug($search, '_') : null;
        $fileName = 'cashflow_' . ($safeSearch ? $safeSearch . '_' : '') . $safeType . '_' . $startDate . '_sampai_' . $endDate . '.xlsx';

        $border = OpenSpoutStyleFactory::thinBorder();
        $headerStyle = OpenSpoutStyleFactory::headerStyle($border);
        $rowStyle = OpenSpoutStyleFactory::rowStyle($border);

        $headers = [
            'Nama',
            'Username',
            'Tipe',
            'Tanggal',
            'Value',
        ];

        $rows = $cashflows->map(function (Cashflow $cashflow) {
            $dateValue = $cashflow->date;
            $dateText = method_exists($dateValue, 'format') ? $dateValue->format('Y-m-d') : (string) $dateValue;

            return [
                $cashflow->user?->name ?? '',
                $cashflow->user?->username ?? '',
                $cashflow->type,
                $dateText,
                (int) $cashflow->value,
            ];
        });

        return $this->xlsxExporter->download($fileName, $headers, $rows, $headerStyle, $rowStyle, autoFitColumns: true);
    }
}
