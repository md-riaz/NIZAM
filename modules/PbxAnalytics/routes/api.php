<?php

use App\Http\Controllers\Api\RecordingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PBX Analytics Module Routes
|--------------------------------------------------------------------------
|
| Recordings, CDR Analytics
|
*/

Route::prefix('api')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        // Recordings
        Route::get('recordings', [RecordingController::class, 'index'])->name('recordings.index');
        Route::get('recordings/{recording}', [RecordingController::class, 'show'])->name('recordings.show');
        Route::get('recordings/{recording}/download', [RecordingController::class, 'download'])->name('recordings.download');
        Route::delete('recordings/{recording}', [RecordingController::class, 'destroy'])->name('recordings.destroy');
    });
});
