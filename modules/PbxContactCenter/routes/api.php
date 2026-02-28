<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\QueueMetricsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PBX Contact Center Module Routes
|--------------------------------------------------------------------------
|
| Agents, Queues, Queue Metrics, Wallboard
|
*/

Route::prefix('api')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        // Agents
        Route::apiResource('agents', AgentController::class);
        Route::post('agents/{agent}/state', [AgentController::class, 'changeState'])->name('agents.state');

        // Queues
        Route::apiResource('queues', QueueController::class);
        Route::post('queues/{queue}/members', [QueueController::class, 'addMember'])->name('queues.add-member');
        Route::delete('queues/{queue}/members/{agent}', [QueueController::class, 'removeMember'])->name('queues.remove-member');
        Route::get('queues/{queue}/members', [QueueController::class, 'members'])->name('queues.members');

        // Queue metrics
        Route::get('queues/{queue}/metrics/realtime', [QueueMetricsController::class, 'realtime'])->name('queues.metrics.realtime');
        Route::post('queues/{queue}/metrics/aggregate', [QueueMetricsController::class, 'aggregate'])->name('queues.metrics.aggregate');
        Route::get('queues/{queue}/metrics/history', [QueueMetricsController::class, 'history'])->name('queues.metrics.history');

        // Wallboard
        Route::get('wallboard', [QueueMetricsController::class, 'wallboard'])->name('wallboard');
        Route::get('agent-states', [QueueMetricsController::class, 'agentStates'])->name('agent-states');
    });
});
