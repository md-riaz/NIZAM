<?php

use App\Http\Controllers\Api\DeviceProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PBX Provisioning Module Routes
|--------------------------------------------------------------------------
|
| Device Profiles, Provisioning
|
*/

Route::prefix('api')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        Route::apiResource('device-profiles', DeviceProfileController::class);
    });
});
