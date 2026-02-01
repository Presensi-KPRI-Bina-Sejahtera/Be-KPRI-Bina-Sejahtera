<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});
