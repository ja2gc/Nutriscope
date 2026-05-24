<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\RND\PatientController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:RND'])->prefix('rnd')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/ncp-records', [PatientController::class, 'ncpRecords']);
});

Route::middleware(['auth:sanctum', 'role:FSS'])->prefix('fss')->group(function () {
    // FSS routes here
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('admin')->group(function () {
    // Admin routes here
});
