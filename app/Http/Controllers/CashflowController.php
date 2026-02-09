<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cashflow;
use App\Services\Exports\CashflowExportService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;

class CashflowController extends Controller
{
    public function index(Request $request) : Response
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'type' => ['sometimes', 'string', 'in:pemasukan,pengeluaran'],
        ], [
            'per_page.integer' => 'per_page harus berupa angka',
            'per_page.min' => 'per_page minimal 1',
            'search.string' => 'Search harus berupa teks',
            'start_date.date' => 'Tanggal mulai harus berupa tanggal yang valid',
            'end_date.date' => 'Tanggal selesai harus berupa tanggal yang valid',
            'type.in' => 'Type harus salah satu dari: pemasukan, pengeluaran',
            'end_date.after_or_equal' => 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = $validated['search'] ?? null;
        $types = $validated['type'] ?? null;

        // Default: tanggal 1 bulan ini jika tidak ada start_date
        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? today()->toDateString();

        // Base query untuk filter (dipakai paginate + summary)
        $baseQuery = Cashflow::query();
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->orWhereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            });
        }

        if (!empty($types)) {
            $baseQuery->where('type', $types);
        }

        if ($startDate) {
            $baseQuery->whereDate('date', '>=', $startDate);
        }

        if ($endDate) {
            $baseQuery->whereDate('date', '<=', $endDate);
        }

        $summaryRow = (clone $baseQuery)
            ->reorder()
            ->selectRaw('SUM(CASE WHEN type = "pemasukan" THEN value ELSE 0 END) as sum_pemasukan')
            ->selectRaw('SUM(CASE WHEN type = "pengeluaran" THEN value ELSE 0 END) as sum_pengeluaran')
            ->first();

        $summary = [
            'pemasukan' => (int) ($summaryRow->sum_pemasukan ?? 0),
            'pengeluaran' => (int) ($summaryRow->sum_pengeluaran ?? 0),
        ];

        $query = (clone $baseQuery)
            ->with(['user:id,name,username,profile_image'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $cashflows = $query->paginate($perPage);

        $cashflowData = collect($cashflows->items())->map(function (Cashflow $cashflow) {
            return [
                'user' => [
                    'id' => (int) $cashflow->user->id,
                    'name' => $cashflow->user->name,
                    'username' => $cashflow->user->username,
                    'profile_image' => $cashflow->user->getPhotoProfile(),
                ],
                'type' => $cashflow->type,
                'date' => $cashflow->date->format('Y-m-d'),
                'value' => (int) $cashflow->value,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar cashflow berhasil diambil',
            'data' => [
                'current_page' => (int) $cashflows->currentPage(),
                'last_page' => (int) $cashflows->lastPage(),
                'per_page' => (int) $cashflows->perPage(),
                'total' => (int) $cashflows->total(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'summary' => $summary,
                'cashflows' => $cashflowData,
            ],
        ], 200);
    }

    /**
     * Export daftar cashflow (admin) ke Excel.
     * GET /api/admin/cashflow/export-excel
     */
    public function exportExcel(Request $request, CashflowExportService $exportService): StreamedResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'string'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'type' => ['sometimes', 'string', 'in:pemasukan,pengeluaran'],
        ], [
            'search.string' => 'Search harus berupa teks',
            'start_date.date' => 'Tanggal mulai harus berupa tanggal yang valid',
            'end_date.date' => 'Tanggal selesai harus berupa tanggal yang valid',
            'type.in' => 'Type harus salah satu dari: pemasukan, pengeluaran',
            'end_date.after_or_equal' => 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai',
        ]);

        return $exportService->exportCashflowToXlsx($validated);
    }

    /**
     * Simpan data cashflow baru per hari itu
     * POST /api/cashflows
     */
    public function store(Request $request) : Response
    {
        $validated = $request->validate([
            'pemasukan' => ['required', 'integer', 'min:0'],
            'pengeluaran' => ['required', 'integer', 'min:0'],
        ], [
            'pemasukan.required' => 'Pemasukan wajib diisi',
            'pemasukan.integer' => 'Pemasukan harus berupa angka',
            'pemasukan.min' => 'Pemasukan minimal 0',
            'pengeluaran.required' => 'Pengeluaran wajib diisi',
            'pengeluaran.integer' => 'Pengeluaran harus berupa angka',
            'pengeluaran.min' => 'Pengeluaran minimal 0',
        ]);
        $user = $request->user();

        if (Cashflow::where('user_id', $user->id)
            ->whereDate('date', today()->toDateString())
            ->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data cashflow untuk hari ini sudah ada',
            ], 400);
        }

        DB::beginTransaction();
        $pemasukan = Cashflow::create([
            'user_id' => $user->id,
            'type' => 'pemasukan',
            'date' => today()->toDateString(),
            'value' => $validated['pemasukan'],
        ]);
        $pengeluaran = Cashflow::create([
            'user_id' => $user->id,
            'type' => 'pengeluaran',
            'date' => today()->toDateString(),
            'value' => $validated['pengeluaran'],
        ]);

        if (!$pemasukan || !$pengeluaran) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan data cashflow untuk hari ini',
            ], 500);
        }

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Data cashflow untuk hari ini berhasil disimpan',
            'data' => [
                'pemasukan' => [
                    'date' => $pemasukan->date->format('Y-m-d'),
                    'value' => (int) $pemasukan->value,
                ],
                'pengeluaran' => [
                    'date' => $pengeluaran->date->format('Y-m-d'),
                    'value' => (int) $pengeluaran->value,
                ],
            ],
        ], 201);
    }

    public function todayCashflows(Request $request) : Response
    {
        $user = $request->user();

        $pemasukan = Cashflow::where('user_id', $user->id)
            ->where('type', 'pemasukan')
            ->whereDate('date', today()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $pengeluaran = Cashflow::where('user_id', $user->id)
            ->where('type', 'pengeluaran')
            ->whereDate('date', today()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar cashflow hari ini berhasil diambil',
            'data' => [
                'pemasukan' => [
                    'date' => $pemasukan->date->format('Y-m-d'),
                    'value' => (int) $pemasukan->value,
                ],
                'pengeluaran' => [
                    'date' => $pengeluaran->date->format('Y-m-d'),
                    'value' => (int) $pengeluaran->value,
                ],
            ],
        ], 200);
    }
}
