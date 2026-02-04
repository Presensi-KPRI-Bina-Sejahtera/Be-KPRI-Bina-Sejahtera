<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\Rule;

class DepositController extends Controller
{
    /**
     * Ambil daftar semua deposit.
     * GET /api/admin/deposit
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'type' => ['sometimes', 'string', 'in:angsuran,simpanan'],
            'status' => ['sometimes', 'string', 'in:pending,verified'],
        ], [
            'per_page.integer' => 'per_page harus berupa angka',
            'per_page.min' => 'per_page minimal 1',
            'search.string' => 'Search harus berupa teks',
            'start_date.date' => 'Tanggal mulai harus berupa tanggal yang valid',
            'end_date.date' => 'Tanggal selesai harus berupa tanggal yang valid',
            'type.in' => 'Type harus salah satu dari: angsuran, simpanan',
            'status.in' => 'Status harus salah satu dari: pending, verified',
            'end_date.after_or_equal' => 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $search = $validated['search'] ?? null;
        $types = $validated['type'] ?? null;
        $statuses = $validated['status'] ?? null;

        // Default: tanggal 1 bulan ini jika tidak ada start_date
        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? today()->toDateString();

        // Base query untuk filter (dipakai paginate + summary)
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

        if (!empty($types)) {
            $baseQuery->where('type', $types);
        }

        if (!empty($statuses)) {
            if ($statuses === 'pending') {
                $baseQuery->whereNull('verified_key');
            } elseif ($statuses === 'verified') {
                $baseQuery->whereNotNull('verified_key');
            }
        }

        if ($startDate) {
            $baseQuery->whereDate('date', '>=', $startDate);
        }

        if ($endDate) {
            $baseQuery->whereDate('date', '<=', $endDate);
        }

        $summaryRow = (clone $baseQuery)
            ->reorder()
            ->selectRaw('SUM(CASE WHEN type = "simpanan" THEN value ELSE 0 END) as sum_simpanan')
            ->selectRaw('SUM(CASE WHEN type = "angsuran" THEN value ELSE 0 END) as sum_angsuran')
            ->first();

        $summary = [
            'simpanan' => (int) ($summaryRow->sum_simpanan ?? 0),
            'angsuran' => (int) ($summaryRow->sum_angsuran ?? 0),
        ];

        $query = (clone $baseQuery)
            ->with(['user:id,name,username,profile_image'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        $deposits = $query->paginate($perPage);

        $depositData = collect($deposits->items())->map(function (Deposit $deposit) {
            $isVerified = !is_null($deposit->verified_key);
            return [
                'id' => (int) $deposit->id,
                'user' => [
                    'id' => (int) $deposit->user->id,
                    'name' => $deposit->user->name,
                    'username' => $deposit->user->username,
                    'profile_image' => $deposit->user->getPhotoProfile(),
                ],
                'for_name' => $deposit->for_name,
                'type' => $deposit->type,
                'date' => $deposit->date->format('Y-m-d'),
                'value' => (int) $deposit->value,
                'verified_key' => $deposit->verified_key,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar deposit berhasil diambil',
            'data' => [
                'current_page' => (int) $deposits->currentPage(),
                'last_page' => (int) $deposits->lastPage(),
                'per_page' => (int) $deposits->perPage(),
                'total' => (int) $deposits->total(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'summary' => $summary,
                'deposits' => $depositData,
            ],
        ], 200);
    }

    /**
     * Verifikasi atau edit verified key deposit berdasarkan ID.
     * PATCH /api/admin/deposit/verify/{id}
     */
    public function verify(Request $request, $id): Response
    {
        $deposit = Deposit::findOrFail($id);

        $validated = $request->validate([
            'verified_key' => ['required', 'string', 'max:255', Rule::unique('deposits', 'verified_key')],
        ], [
            'verified_key.required' => 'Verified key wajib diisi',
            'verified_key.string' => 'Verified key harus berupa teks',
            'verified_key.max' => 'Verified key maksimal :max karakter',
            'verified_key.unique' => 'Verified key sudah digunakan pada deposit lain',
        ]);

        $isEdit = !is_null($deposit->verified_key);

        $deposit->update([
            'verified_key' => $validated['verified_key'],
        ]);

        $deposit->refresh();
        $deposit->load(['user:id,name,username,profile_image']);

        return response()->json([
            'status' => 'success',
            'message' => $isEdit ? 'Berhasil memperbarui kode verifikasi' : 'Berhasil memverifikasi setoran',
            'data' => [
                'id' => (int) $deposit->id,
                'user' => [
                    'id' => (int) $deposit->user->id,
                    'name' => $deposit->user->name,
                    'username' => $deposit->user->username,
                    'profile_image' => $deposit->user->getPhotoProfile(),
                ],
                'for_name' => $deposit->for_name,
                'type' => $deposit->type,
                'date' => $deposit->date->format('Y-m-d'),
                'value' => (int) $deposit->value,
                'verified_key' => $deposit->verified_key,
            ],
        ], 200);
    }
}
