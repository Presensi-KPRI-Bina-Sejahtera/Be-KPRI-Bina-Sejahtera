<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/**
 * Status check route.
 * Get /api/status
 */
Route::get('/status', function () {
    return response()->json(['status' => 'ok']);
});

/**
 * Authentication routes.
 * Post /api/auth/login -> login manual pake password
 * Get /api/auth/me -> get current user dan status login
 * Post /api/auth/logout -> logout user
 */
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login-google', [AuthController::class, 'loginGoogle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/**
 * Admin-only routes go here.
 */
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Example: Manage users
});
