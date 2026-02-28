<?php

use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CallEventController;
use App\Http\Controllers\Api\CallEventStreamController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PBX Automation Module Routes
|--------------------------------------------------------------------------
|
| Webhooks, Call Events, Call Control
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        // Webhooks
        Route::apiResource('webhooks', WebhookController::class);
        Route::get('webhooks/{webhook}/delivery-attempts', [WebhookController::class, 'deliveryAttempts'])
            ->name('webhooks.delivery-attempts');
        Route::get('webhooks/{webhook}/delivery-stats', [WebhookController::class, 'deliveryStats'])
            ->name('webhooks.delivery-stats');

        // Call events
        Route::get('call-events', [CallEventController::class, 'index'])->name('call-events.index');
        Route::get('call-events/stream', [CallEventStreamController::class, 'stream'])->name('call-events.stream');
        Route::get('call-events/{callUuid}/trace', [CallEventController::class, 'trace'])->name('call-events.trace');
        Route::get('call-events/replay/{eventId}', [CallEventController::class, 'replay'])->name('call-events.replay');
        Route::post('call-events/redispatch/{eventId}', [CallEventController::class, 'redispatch'])->name('call-events.redispatch');

        // Call control
        Route::post('calls/originate', [CallController::class, 'originate'])->name('calls.originate');
        Route::get('calls/status', [CallController::class, 'status'])->name('calls.status');
        Route::post('calls/hangup', [CallController::class, 'hangup'])->name('calls.hangup');
        Route::post('calls/transfer', [CallController::class, 'transfer'])->name('calls.transfer');
        Route::post('calls/recording', [CallController::class, 'toggleRecording'])->name('calls.recording');
        Route::post('calls/hold', [CallController::class, 'hold'])->name('calls.hold');
    });
});
