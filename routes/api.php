<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

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
 * Admin api routes.
 */
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    /**
     * User management routes.
     * Get /api/admin/user -> list all users
     * Post /api/admin/user -> create new user
     * Put /api/admin/user/{id} -> update user by id
     * Delete /api/admin/user/{id} -> delete user by id
     * Get /api/admin/user/dropdown -> get users for dropdown
     */
    Route::prefix('user')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::get('/dropdown', [UserController::class, 'dropdown']);
    });
});
