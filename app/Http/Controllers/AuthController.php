<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * Login manual dengan email/username dan password.
     * Post /api/auth/login
     */
    public function login(Request $request): Response
    {
        $validated = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.required' => 'Email atau username wajib diisi.',
            'email.string' => 'Email atau username harus berupa teks.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password harus berupa teks.',
            'password.min' => 'Password minimal :min karakter.',
        ]);

        $email = $validated['email'];
        $user = User::query()
            ->where('email', $email)
            ->orWhere('username', $email)
            ->first();

        if (!$user || !$user->password || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email/username atau password salah.',
            ], 401);
        }

        $tokenName = $validated['device_name'] ?? 'api';

        $token = $user->createToken(
            name: $tokenName,
            abilities: ['*'],
            expiresAt: now()->addDay(3)
        )->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'token' => $token,
                'user' => [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ],
        ]);
    }

    public function loginGoogle(Request $request): Response
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ], [
            'id_token.required' => 'ID token wajib diisi.',
            'id_token.string' => 'ID token harus berupa teks.',
            'device_name.string' => 'Device name harus berupa teks.',
            'device_name.max' => 'Device name maksimal :max karakter.',
        ]);

        $googleClientId = (string) config('services.google.client_id', '');
        if ($googleClientId === '') {
            abort(500, 'Server belum dikonfigurasi: GOOGLE_CLIENT_ID belum di-set.');
        }

        $tokenInfoResponse = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $validated['id_token'],
        ]);

        if (!$tokenInfoResponse->successful()) {
            abort(401, 'Google token tidak valid.');
        }

        $payload = $tokenInfoResponse->json();

        if (($payload['aud'] ?? null) !== $googleClientId) {
            abort(401, 'Google token audience tidak sesuai.');
        }

        if (!isset($payload['email'], $payload['sub'])) {
            abort(401, 'Google token payload tidak lengkap.');
        }

        $user = User::query()->where('email', $payload['email'])->first();
        if (!$user) {
            abort(404, 'Pengguna dengan email tersebut tidak ditemukan.');
        }

        $user->forceFill([
            'profile_image' => $payload['picture'] ?? $user->profile_image,
            'provider' => 'google',
            'id_provider' => $payload['sub'],
        ])->save();

        $tokenName = $validated['device_name'] ?? 'google-login';

        $token = $user->createToken(
            name: $tokenName,
            abilities: ['*'],
            expiresAt: now()->addDay(3)
        )->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'token' => $token,
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request): Response
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mengambil data user.',
            'data' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'profile_image' => $user->profile_image,
            ]
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();

        $accessToken = $user->currentAccessToken();
        if ($accessToken) {
            $accessToken->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil logout.',
        ]);
    }
}
