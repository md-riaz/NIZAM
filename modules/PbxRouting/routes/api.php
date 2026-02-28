<?php

use App\Http\Controllers\Api\DidController;
use App\Http\Controllers\Api\IvrController;
use App\Http\Controllers\Api\RingGroupController;
use App\Http\Controllers\Api\TimeConditionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PBX Routing Module Routes
|--------------------------------------------------------------------------
|
| DIDs, Ring Groups, IVR, Time Conditions
|
*/

Route::prefix('api')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        Route::apiResource('dids', DidController::class);
        Route::apiResource('ring-groups', RingGroupController::class);
        Route::apiResource('ivrs', IvrController::class);
        Route::apiResource('time-conditions', TimeConditionController::class);
    });
});
