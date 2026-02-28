<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CallDetailRecordController;
use App\Http\Controllers\Api\CallEventController;
use App\Http\Controllers\Api\CallEventStreamController;
use App\Http\Controllers\Api\CallFlowController;
use App\Http\Controllers\Api\CallRoutingPolicyController;
use App\Http\Controllers\Api\DeviceProfileController;
use App\Http\Controllers\Api\DidController;
use App\Http\Controllers\Api\ExtensionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\IvrController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\QueueMetricsController;
use App\Http\Controllers\Api\RecordingController;
use App\Http\Controllers\Api\RingGroupController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantStatsController;
use App\Http\Controllers\Api\TimeConditionController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');

Route::middleware('throttle:5,1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    // Token management
    Route::get('auth/tokens', [TokenController::class, 'index'])->name('auth.tokens.index');
    Route::post('auth/tokens', [TokenController::class, 'store'])->name('auth.tokens.store');
    Route::delete('auth/tokens/{tokenId}', [TokenController::class, 'destroy'])->name('auth.tokens.destroy');

    Route::apiResource('tenants', TenantController::class);
    Route::get('tenants/{tenant}/settings', [TenantController::class, 'settings'])->name('tenants.settings');
    Route::put('tenants/{tenant}/settings', [TenantController::class, 'updateSettings'])->name('tenants.settings.update');
    Route::post('tenants/provision', [TenantController::class, 'provision'])->name('tenants.provision');

    // Admin observability dashboard
    Route::get('admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');

    // User management (admin-only)
    Route::apiResource('users', UserController::class);
    Route::get('users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
    Route::post('users/{user}/permissions/grant', [UserController::class, 'grantPermissions'])->name('users.permissions.grant');
    Route::post('users/{user}/permissions/revoke', [UserController::class, 'revokePermissions'])->name('users.permissions.revoke');
    Route::get('permissions', [UserController::class, 'availablePermissions'])->name('permissions.index');

    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        Route::get('stats', TenantStatsController::class)->name('tenants.stats');

        // Usage metering
        Route::get('usage/summary', [UsageController::class, 'summary'])->name('tenants.usage.summary');
        Route::post('usage/collect', [UsageController::class, 'collect'])->name('tenants.usage.collect');
        Route::get('usage/reconcile', [UsageController::class, 'reconcile'])->name('tenants.usage.reconcile');

        Route::apiResource('extensions', ExtensionController::class);
        Route::apiResource('dids', DidController::class);
        Route::apiResource('ring-groups', RingGroupController::class);
        Route::apiResource('ivrs', IvrController::class);
        Route::apiResource('time-conditions', TimeConditionController::class);
        Route::get('cdrs/export', [CallDetailRecordController::class, 'export'])->name('cdrs.export');
        Route::apiResource('cdrs', CallDetailRecordController::class)->only(['index', 'show']);
        Route::apiResource('device-profiles', DeviceProfileController::class);
        Route::apiResource('webhooks', WebhookController::class);
        Route::get('webhooks/{webhook}/delivery-attempts', [WebhookController::class, 'deliveryAttempts'])
            ->name('webhooks.delivery-attempts');
        Route::get('webhooks/{webhook}/delivery-stats', [WebhookController::class, 'deliveryStats'])
            ->name('webhooks.delivery-stats');

        Route::apiResource('call-routing-policies', CallRoutingPolicyController::class);
        Route::post('call-routing-policies/{call_routing_policy}/evaluate', [CallRoutingPolicyController::class, 'evaluate'])
            ->name('call-routing-policies.evaluate');
        Route::apiResource('call-flows', CallFlowController::class);

        // Recordings
        Route::get('recordings', [RecordingController::class, 'index'])->name('recordings.index');
        Route::get('recordings/{recording}', [RecordingController::class, 'show'])->name('recordings.show');
        Route::get('recordings/{recording}/download', [RecordingController::class, 'download'])->name('recordings.download');
        Route::delete('recordings/{recording}', [RecordingController::class, 'destroy'])->name('recordings.destroy');

        Route::get('call-events', [CallEventController::class, 'index'])->name('call-events.index');
        Route::get('call-events/stream', [CallEventStreamController::class, 'stream'])->name('call-events.stream');
        Route::get('call-events/{callUuid}/trace', [CallEventController::class, 'trace'])->name('call-events.trace');
        Route::get('call-events/replay/{eventId}', [CallEventController::class, 'replay'])->name('call-events.replay');
        Route::post('call-events/redispatch/{eventId}', [CallEventController::class, 'redispatch'])->name('call-events.redispatch');

        Route::post('calls/originate', [CallController::class, 'originate'])->name('calls.originate');
        Route::get('calls/status', [CallController::class, 'status'])->name('calls.status');
        Route::post('calls/hangup', [CallController::class, 'hangup'])->name('calls.hangup');
        Route::post('calls/transfer', [CallController::class, 'transfer'])->name('calls.transfer');
        Route::post('calls/recording', [CallController::class, 'toggleRecording'])->name('calls.recording');
        Route::post('calls/hold', [CallController::class, 'hold'])->name('calls.hold');

        // Audit logs (read-only)
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

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
