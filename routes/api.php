<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExtensionController;
use App\Http\Controllers\Api\DidController;
use App\Http\Controllers\Api\RingGroupController;
use App\Http\Controllers\Api\IvrController;
use App\Http\Controllers\Api\TimeConditionController;
use App\Http\Controllers\Api\CallDetailRecordController;
use App\Http\Controllers\Api\DeviceProfileController;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::apiResource('tenants', TenantController::class);

    Route::prefix('tenants/{tenant}')->group(function () {
        Route::apiResource('extensions', ExtensionController::class);
        Route::apiResource('dids', DidController::class);
        Route::apiResource('ring-groups', RingGroupController::class);
        Route::apiResource('ivrs', IvrController::class);
        Route::apiResource('time-conditions', TimeConditionController::class);
        Route::apiResource('cdrs', CallDetailRecordController::class)->only(['index', 'show']);
        Route::apiResource('device-profiles', DeviceProfileController::class);
    });
});
