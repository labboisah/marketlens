<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MonitoringController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.version', 'throttle:api'])->group(function (): void {
    Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->middleware(['auth:sanctum', 'account.active'])->name('user');
    });

    Route::prefix('device')->name('api.v1.device.')->middleware('throttle:device')->group(function (): void {
        Route::post('/activate', [DeviceController::class, 'activate'])->name('activate');
        Route::get('/status', [DeviceController::class, 'status'])->name('status');
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat'])->name('heartbeat');
        Route::get('/versions', [DeviceController::class, 'versions'])->name('versions');
    });

    Route::prefix('sync')->name('api.v1.sync.')->middleware(['auth:sanctum', 'throttle:sync'])->group(function (): void {
        Route::post('/batches', [SyncController::class, 'store'])->name('batches.store');
        Route::get('/batches/{batch}', [SyncController::class, 'show'])->name('batches.show');
    });

    Route::prefix('monitoring')->name('api.v1.monitoring.')->middleware(['auth:sanctum', 'account.active', 'throttle:monitoring'])->group(function (): void {
        Route::get('/dashboard', [MonitoringController::class, 'dashboard'])->name('dashboard');
        Route::get('/branches', [MonitoringController::class, 'branches'])->name('branches');
    });
});
