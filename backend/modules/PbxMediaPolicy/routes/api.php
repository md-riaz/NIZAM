<?php

use App\Http\Controllers\Api\CodecMetricsController;
use App\Http\Controllers\Api\GatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PBX Media Policy Module Routes
|--------------------------------------------------------------------------
|
| Gateways, Codec Metrics
|
*/

Route::prefix('api/v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        Route::apiResource('gateways', GatewayController::class);
        Route::get('codec-metrics', CodecMetricsController::class)->name('codec-metrics.index');
    });
});
