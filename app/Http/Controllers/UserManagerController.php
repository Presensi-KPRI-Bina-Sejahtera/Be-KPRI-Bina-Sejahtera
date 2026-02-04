<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserManagerController extends Controller
{
    /**
     * Ambil daftar semua user.
     * GET /admin/user
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'role' => ['sometimes', Rule::in(['employee', 'admin'])],
            'search' => ['sometimes', 'string'],
        ], [
            'per_page.integer' => 'per_page harus berupa angka',
            'per_page.min' => 'per_page minimal 1',
            'role.in' => 'Role harus salah satu dari: employee, admin',
            'search.string' => 'Search harus berupa teks',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $query = User::with('presenceLocation')->orderBy('id', 'asc');
        $search = $validated['search'] ?? null;
        $role = $validated['role'] ?? null;

        if ($role) {
            $query->where('role', $role);
        }
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('username', 'like', "%$search%");
            });
        }

        $users = $query->paginate($perPage);
        $userData = collect($users->items())->map(function ($user) {
            return [
                'id' => (int) $user->id,
                'presence_location_id' => $user->presence_location_id !== null ? (int) $user->presence_location_id : null,
                'presence_location_name' => $user->presenceLocation?->name,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
            ];
        });
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar user berhasil diambil',
            'data' => [
                'current_page' => (int) $users->currentPage(),
                'last_page' => (int) $users->lastPage(),
                'per_page' => (int) $users->perPage(),
                'total' => (int) $users->total(),
                'users' => $userData,
            ],
        ], 200);
    }

    /**
     * Ambil daftar user untuk dropdown.
     * GET /admin/user/dropdown
     */
    public function dropdown(Request $request): Response
    {
        $role = $request->input('role');
        $query = User::query();

        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->get(['id', 'name']);
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar user untuk dropdown berhasil diambil',
            'data' => $users,
        ], 200);
    }

    /**
     * Tambah user baru.
     * POST /admin/user
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'presence_location_id' => ['nullable', 'integer', 'exists:presence_locations,id', 'required_if:role,employee'],
            'username' => ['nullable', 'string', 'unique:users,username'],
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::in(['employee', 'admin'])],
        ], [
            'presence_location_id.required' => 'ID lokasi presensi wajib diisi',
            'presence_location_id.integer' => 'ID lokasi presensi harus berupa angka',
            'presence_location_id.exists' => 'ID lokasi presensi tidak valid',
            'presence_location_id.required_if' => 'ID lokasi presensi wajib diisi untuk role employee',
            'username.string' => 'Username harus berupa teks',
            'username.unique' => 'Username sudah digunakan',
            'name.required' => 'Nama wajib diisi',
            'name.string' => 'Nama harus berupa teks',
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'role.required' => 'Role wajib diisi',
            'role.in' => 'Role harus salah satu dari: employee, admin',
            'profile_image.string' => 'Profile image harus berupa teks',
            'provider.string' => 'Provider harus berupa teks',
            'id_provider.string' => 'ID provider harus berupa teks',
            'password.string' => 'Password harus berupa teks',
            'password.min' => 'Password minimal :min karakter',
        ]);

        $user = User::create($validated);
        $user->load('presenceLocation');
        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil ditambahkan',
            'data' => [
                'id' => (int) $user->id,
                'presence_location_id' => $user->presence_location_id !== null ? (int) $user->presence_location_id : null,
                'presence_location_name' => $user->presenceLocation?->name,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
            ],
        ], 201);
    }

    /**
     * Update data user.
     * PUT /admin/user/{id}
     */
    public function update(Request $request, $id): Response
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'presence_location_id' => ['sometimes', 'nullable', 'integer', 'exists:presence_locations,id'],
            'username' => ['sometimes', 'nullable', 'string', Rule::unique('users', 'username')->ignore($user->id)],
            'name' => ['sometimes', 'string'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['employee', 'admin'])],
        ], [
            'presence_location_id.integer' => 'ID lokasi presensi harus berupa angka',
            'presence_location_id.exists' => 'ID lokasi presensi tidak valid',
            'username.string' => 'Username harus berupa teks',
            'username.unique' => 'Username sudah digunakan',
            'name.string' => 'Nama harus berupa teks',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah digunakan',
            'role.in' => 'Role harus salah satu dari: employee, admin',
        ]);

        $newRole = $validated['role'] ?? $user->role;
        $newPresenceLocationId = array_key_exists('presence_location_id', $validated)
            ? $validated['presence_location_id']
            : $user->presence_location_id;

        if ($newRole === 'employee' && empty($newPresenceLocationId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID lokasi presensi wajib diisi untuk role employee',
                'errors' => [
                    'presence_location_id' => ['ID lokasi presensi wajib diisi untuk role employee'],
                ],
            ], 422);
        }

        $user->update($validated);
        $user->refresh()->load('presenceLocation');
        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil diupdate',
            'data' => [
                'id' => (int) $user->id,
                'presence_location_id' => $user->presence_location_id !== null ? (int) $user->presence_location_id : null,
                'presence_location_name' => $user->presenceLocation?->name,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
            ],
        ], 200);
    }

    /**
     * Hapus user (soft delete).
     * DELETE /admin/user/{id}
     */
    public function destroy($id): Response
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'User berhasil dihapus',
        ], 200);
    }
}
