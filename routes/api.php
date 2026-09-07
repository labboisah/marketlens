<?php

use App\Domain\Services\Http\Controllers\Platform\ServiceMonitoringController;
use App\Domain\Services\Http\Controllers\Platform\StaffEarningsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MonitoringController;
use App\Http\Controllers\Api\V1\ReleaseManifestController;
use App\Http\Controllers\Api\V1\SyncController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/releases/{application}/manifest', ReleaseManifestController::class)
    ->whereIn('application', ['business-server', 'business-app'])
    ->middleware('throttle:60,1')
    ->name('api.v1.releases.manifest');

Route::prefix('v1/device/restore-points')->middleware('throttle:60,1')->group(function (): void {
    Route::post('/rotate-credential', [\App\Http\Controllers\Api\V1\RestorePointController::class, 'rotateCredential']);
    Route::get('/', [\App\Http\Controllers\Api\V1\RestorePointController::class, 'index']);
    Route::post('/', [\App\Http\Controllers\Api\V1\RestorePointController::class, 'store']);
    Route::get('/{uuid}/download', [\App\Http\Controllers\Api\V1\RestorePointController::class, 'download'])->whereUuid('uuid');
    Route::delete('/{uuid}', [\App\Http\Controllers\Api\V1\RestorePointController::class, 'delete'])->whereUuid('uuid');
    Route::patch('/requests/{uuid}', [\App\Http\Controllers\Api\V1\RestorePointController::class, 'updateRequest'])->whereUuid('uuid');
});

Route::prefix('v1')->middleware(['api.version', 'throttle:api'])->group(function (): void {
    Route::get('/activation/ping', fn () => response()->json([
        'success' => true,
        'message' => 'Activation endpoint reachable.',
        'data' => ['status' => 'ok'],
        'meta' => [],
    ]))->name('api.v1.activation.ping');

    Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
        Route::get('/user', [AuthController::class, 'user'])->middleware(['auth:sanctum', 'account.active'])->name('user');
    });

    Route::prefix('device')->name('api.v1.device.')->middleware('throttle:device')->group(function (): void {
        Route::post('/activate', [DeviceController::class, 'activate'])->name('activate');
        Route::get('/status', [DeviceController::class, 'status'])->name('status');
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat'])->name('heartbeat');
        Route::get('/application-update', [DeviceController::class, 'applicationUpdate'])->name('application-update');
        Route::get('/approved-client-apps', [DeviceController::class, 'approvedClientApps'])->name('approved-client-apps');
        Route::get('/versions', [DeviceController::class, 'versions'])->name('versions');
    });

    Route::prefix('sync')->name('api.v1.sync.')->middleware(['throttle:sync'])->group(function (): void {
        Route::post('/batches', [SyncController::class, 'store'])->name('batches.store');
        Route::get('/batches/{batchUuid}', [SyncController::class, 'show'])->name('batches.show');
        Route::post('/acknowledgements', [SyncController::class, 'acknowledge'])->name('acknowledgements.store');
        Route::get('/checkpoint', [SyncController::class, 'checkpoint'])->name('checkpoint.show');
    });

    Route::prefix('monitoring')->name('api.v1.monitoring.')->middleware(['auth:sanctum', 'account.active', 'throttle:monitoring'])->group(function (): void {
        Route::get('/dashboard', [MonitoringController::class, 'dashboard'])->name('dashboard');
        Route::get('/branches', [MonitoringController::class, 'branches'])->name('branches');
        Route::get('/services', [MonitoringController::class, 'services'])->name('services');
        Route::get('/shareholders', [MonitoringController::class, 'shareholders'])->name('shareholders');
        // Extended services monitoring endpoints for Monitoring App
        Route::prefix('services')->group(function (): void {
            Route::get('/dashboard', [ServiceMonitoringController::class, 'dashboard'])->name('services.dashboard');
            Route::get('/orders', [ServiceMonitoringController::class, 'orders'])->name('services.orders');
            Route::get('/orders/{uuid}', [ServiceMonitoringController::class, 'showOrder'])->name('services.orders.show');
            Route::get('/staff-performance', [ServiceMonitoringController::class, 'dashboard'])->name('services.staff_performance');
            Route::get('/staff-earnings', [StaffEarningsController::class, 'index'])->name('services.staff_earnings');
            Route::get('/revenue-sharing', [ServiceMonitoringController::class, 'allocations'])->name('services.revenue_sharing');
            Route::get('/settlements', [ServiceMonitoringController::class, 'settlements'])->name('services.settlements');
        });

        // Shareholders & profit monitoring endpoints
        Route::prefix('shareholders')->group(function (): void {
            Route::get('/', [MonitoringController::class, 'shareholders'])->name('shareholders.index');
            Route::get('/ownership', [MonitoringController::class, 'ownership'])->name('shareholders.ownership');
            Route::get('/profit-periods', [MonitoringController::class, 'profitPeriods'])->name('shareholders.profit_periods');
            Route::get('/profit-distributions', [MonitoringController::class, 'profitDistributions'])->name('shareholders.profit_distributions');
            Route::get('/{uuid}', [MonitoringController::class, 'shareholder'])->name('shareholders.show');
        });
    });

    // Services monitoring (read-only)
    Route::prefix('services')->middleware(['auth:sanctum', 'account.active', 'throttle:monitoring'])->group(function () {
        Route::get('/', [ServiceMonitoringController::class, 'dashboard']);
        Route::get('/orders', [ServiceMonitoringController::class, 'orders']);
        Route::get('/orders/{uuid}', [ServiceMonitoringController::class, 'showOrder']);
        Route::get('/payments', [ServiceMonitoringController::class, 'payments']);
        Route::get('/expenses', [ServiceMonitoringController::class, 'expenses']);
        Route::get('/allocations', [ServiceMonitoringController::class, 'allocations']);
        Route::get('/settlements', [ServiceMonitoringController::class, 'settlements']);
    });

    // Staff earnings and settlements (read-only)
    Route::prefix('staff-earnings')->middleware(['auth:sanctum', 'account.active', 'throttle:monitoring'])->group(function () {
        Route::get('/', [StaffEarningsController::class, 'index']);
        Route::get('/{staffUuid}', [StaffEarningsController::class, 'show']);
        Route::get('/settlements', [StaffEarningsController::class, 'settlements']);
    });
});
