<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserManagerController;
use App\Http\Controllers\PresenceLocationController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\CashflowController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ProfileController;

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
 * Post /api/auth/logout -> logout user
 */
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/login-google', [AuthController::class, 'loginGoogle']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/**
 * Profile routes for authenticated users.
 * Get /api/profile/me -> get current user profile
 * Put /api/profile/update -> update current user profile
 * Put /api/profile/update-password -> update current user password
 * Put /api/profile/photo -> update current user photo
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('profile')->group(function () {
        Route::get('/me', [ProfileController::class, 'me']);
        Route::put('/update', [ProfileController::class, 'update']);
        Route::put('/update-password', [ProfileController::class, 'updatePassword']);
        Route::put('/photo', [ProfileController::class, 'updatePhoto']);
    });
    Route::get('/address', [PresenceLocationController::class, 'getAddressFromCoordinatesApi']);
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
        Route::get('/', [UserManagerController::class, 'index']);
        Route::post('/', [UserManagerController::class, 'store']);
        Route::put('/{id}', [UserManagerController::class, 'update']);
        Route::delete('/{id}', [UserManagerController::class, 'destroy']);
        Route::get('/dropdown', [UserManagerController::class, 'dropdown']);
    });

    /**
     * Presence Location management routes.
     * Get /api/admin/presence-location -> list all presence locations
     * Post /api/admin/presence-location -> create new presence location
     * Put /api/admin/presence-location/{id} -> update presence location by id
     * Delete /api/admin/presence-location/{id} -> delete presence location by id
     * Get /api/admin/presence-location/dropdown -> get presence locations for dropdown
     * Get /api/admin/presence-location/address -> get address from coordinates
     */
    Route::prefix('presence-location')->group(function () {
        Route::get('/', [PresenceLocationController::class, 'index']);
        Route::post('/', [PresenceLocationController::class, 'store']);
        Route::put('/{id}', [PresenceLocationController::class, 'update']);
        Route::delete('/{id}', [PresenceLocationController::class, 'destroy']);
        Route::get('/dropdown', [PresenceLocationController::class, 'dropdown']);
    });

    /**
     * Deposit management routes.
     * Get /api/admin/deposit -> list all deposits
     * Patch /api/admin/deposit/verify/{id} -> verify deposit by id
     */
    Route::prefix('deposit')->group(function () {
        Route::get('/', [DepositController::class, 'index']);
        Route::patch('verify/{id}', [DepositController::class, 'verify']);
    });

    /**
     * Cashflow management routes.
     * Get /api/admin/cashflow -> list all cashflows
     */
    Route::prefix('cashflow')->group(function () {
        Route::get('/', [CashflowController::class, 'index']);
    });

    /**
     * Attendance management routes.
     * Get /api/admin/attendance -> list all attendances
     */
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
    });
});

/**
 * Employee api routes.
 */
Route::middleware(['auth:sanctum', 'role:employee'])->prefix('employee')->group(function () {

    /**
     * Attendance routes.
     * Get /api/employee/attendance -> get today's attendance
     * Post /api/employee/attendance/check-in -> check in attendance
     * Post /api/employee/attendance/check-out -> check out attendance
     */
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'today']);
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
    });

    /**
     * Deposit routes.
     * Get /api/employee/deposit -> get today's deposits
     * Post /api/employee/deposit -> create new deposit
     */
    Route::prefix('deposit')->group(function () {
        Route::get('/', [DepositController::class, 'todayDeposits']);
        Route::post('/', [DepositController::class, 'store']);
    });

    /**
     * Cashflow routes.
     * Get /api/employee/cashflow -> get today's cashflows
     * Post /api/employee/cashflow -> create new cashflow
     */
    Route::prefix('cashflow')->group(function () {
        Route::get('/', [CashflowController::class, 'todayCashflows']);
        Route::post('/', [CashflowController::class, 'store']);
    });

    /**
     * Presence Location routes.
     * Get /api/employee/presence-location -> get my presence location
     */
    Route::prefix('presence-location')->group(function () {
        Route::get('/', [PresenceLocationController::class, 'myPresenceLocation']);
    });
});
