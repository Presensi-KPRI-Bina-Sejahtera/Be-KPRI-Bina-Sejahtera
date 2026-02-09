<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * Ambil data user yang sedang login.
     * Get /api/profile/me
     */
    public function me(Request $request): Response
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data user',
            'data' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
                'has_password' => (bool) !is_null($user->password),
            ]
        ], 200);
    }

    /**
     * Update profil user yang sedang login.
     * Put /api/profile/update
     */
    public function update(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
        ], [
            'name.string' => 'Nama harus berupa teks',
            'name.max' => 'Nama maksimal :max karakter',
            'email.email' => 'Format email tidak valid',
            'email.max' => 'Email maksimal :max karakter',
            'email.unique' => 'Email sudah digunakan',
            'username.string' => 'Username harus berupa teks',
            'username.max' => 'Username maksimal :max karakter',
            'username.unique' => 'Username sudah digunakan',
        ]);

        if (isset($validated['email']) && $user->password === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Atur password terlebih dahulu untuk mengubah email',
                'errors' => [
                    'email' => ['Atur password terlebih dahulu untuk mengubah email'],
                ],
            ], 422);
        }

        $user->forceFill($validated)->save();
        $user->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diupdate',
            'data' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
                'has_password' => (bool) !is_null($user->password),
            ],
        ], 200);
    }

    /**
     * Update password user yang sedang login.
     * Put /api/profile/update-password
     */
    public function updatePassword(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => [$user->password ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi',
            'current_password.string' => 'Password saat ini harus berupa teks',
            'password.required' => 'Password baru wajib diisi',
            'password.string' => 'Password baru harus berupa teks',
            'password.min' => 'Password baru minimal :min karakter',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok',
        ]);

        if ($user->password) {
            if (!Hash::check((string) $validated['current_password'], (string) $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password saat ini salah',
                    'errors' => [
                        'current_password' => ['Password saat ini salah'],
                    ],
                ], 422);
            }
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diupdate',
            'data' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
                'has_password' => (bool) !is_null($user->password),
            ],
        ], 200);
    }

    /**
     * Update foto profil user yang sedang login.
      * Put /api/profile/photo
     */
    public function updatePhoto(Request $request): Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:512'],
        ], [
            'photo.required' => 'Foto wajib diupload',
            'photo.file' => 'Foto harus berupa file',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format foto harus: jpg, jpeg, png, atau webp',
            'photo.max' => 'Ukuran foto maksimal :max KB',
        ]);

        $photo = $validated['photo'];

        $oldProfileImage = (string) ($user->profile_image ?? '');
        $disk = Storage::disk('public');

        $extension = strtolower((string) $photo->getClientOriginalExtension());
        $extension = $extension !== '' ? $extension : 'jpg';

        $fileName = (string) $user->id . '-' . now()->format('YmdHis') . '-' . Str::random(10) . '.' . $extension;
        $path = $photo->storePubliclyAs('profile-photos', $fileName, 'public');

        $user->forceFill([
            'profile_image' => '/storage/' . $path,
        ])->save();

        // Hapus foto lama hanya jika itu foto internal (hasil upload: /storage/...).
        $pathOnly = $oldProfileImage;
        $isHttpUrl = Str::startsWith($pathOnly, ['http://', 'https://']);
        if ($isHttpUrl) {
            $parsed = parse_url($pathOnly);
            $pathOnly = (string) ($parsed['path'] ?? $pathOnly);
        }

        $isInternal = $oldProfileImage !== '' && (
            Str::startsWith($oldProfileImage, ['/storage/', 'storage/']) ||
            Str::startsWith($pathOnly, ['/storage/', 'storage/'])
        );

        if ($isInternal) {
            $diskPath = null;
            if (Str::startsWith($pathOnly, '/storage/')) {
                $diskPath = Str::after($pathOnly, '/storage/');
            } elseif (Str::startsWith($pathOnly, 'storage/')) {
                $diskPath = Str::after($pathOnly, 'storage/');
            }

            if (is_string($diskPath) && $diskPath !== '' && $disk->exists($diskPath)) {
                $disk->delete($diskPath);
            }
        }

        $user->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Foto profil berhasil diupdate',
            'data' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->getPhotoProfile(),
                'has_password' => (bool) !is_null($user->password),
            ],
        ], 200);
    }
}
