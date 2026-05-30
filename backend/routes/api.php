<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\RND\AnnouncementController as RndAnnouncementController;
use App\Http\Controllers\RND\PatientController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:RND', 'audit'])->prefix('rnd')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/ncp-records', [PatientController::class, 'ncpRecords']);
    Route::post('patients/{patient}/ncp-records', [PatientController::class, 'startNcpCycle']);
    Route::apiResource('announcements', RndAnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:FSS'])->prefix('fss')->group(function () {
    // FSS routes here
});

Route::middleware(['auth:sanctum', 'role:Admin'])->prefix('admin')->group(function () {
    Route::apiResource('announcements', AdminAnnouncementController::class)->only(['index', 'store', 'update', 'destroy']);
});
