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

Route::prefix('api/v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('organizations/{organization}')->middleware('organization.access')->group(function () {
        Route::apiResource('device-profiles', DeviceProfileController::class);
    });
});
