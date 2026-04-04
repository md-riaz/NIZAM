<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BridgeController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\CallDetailRecordController;
use App\Http\Controllers\Api\CallEventController;
use App\Http\Controllers\Api\CallEventStreamController;
use App\Http\Controllers\Api\CallRoutingPolicyController;
use App\Http\Controllers\Api\CallSessionController;
use App\Http\Controllers\Api\CdrAnalyticsController;
use App\Http\Controllers\Api\CdrExportController;
use App\Http\Controllers\Api\CodecMetricsController;
use App\Http\Controllers\Api\DeviceProfileController;
use App\Http\Controllers\Api\DidController;
use App\Http\Controllers\Api\ExtensionController;
use App\Http\Controllers\Api\FlowController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HolidayCalendarController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\IvrController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\QueueMetricsController;
use App\Http\Controllers\Api\RecordingController;
use App\Http\Controllers\Api\RegistrationStatusController;
use App\Http\Controllers\Api\RingGroupController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SslController;
use App\Http\Controllers\Api\SystemMediaController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantStatsController;
use App\Http\Controllers\Api\TimeConditionController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core API Routes
|--------------------------------------------------------------------------
|
| These routes are part of the NIZAM core platform kernel.
| Module-specific routes are loaded from routes/modules/ via the module system.
|
*/

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

    // SSL management
    Route::get('admin/ssl', [SslController::class, 'index'])->name('admin.ssl.index');
    Route::put('admin/ssl', [SslController::class, 'update'])->name('admin.ssl.update');
    Route::post('admin/ssl/request', [SslController::class, 'requestCertificate'])->name('admin.ssl.request');

    // User management (admin-only)
    Route::apiResource('users', UserController::class);
    Route::get('users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
    Route::post('users/{user}/permissions/grant', [UserController::class, 'grantPermissions'])->name('users.permissions.grant');
    Route::post('users/{user}/permissions/revoke', [UserController::class, 'revokePermissions'])->name('users.permissions.revoke');
    Route::get('permissions', [UserController::class, 'availablePermissions'])->name('permissions.index');

    // FreeSWITCH Security & Configuration (Superadmin)
    Route::apiResource('admin/sip-profiles', \App\Http\Controllers\Api\SipProfileController::class);
    Route::apiResource('admin/blocked-destinations', \App\Http\Controllers\Api\BlockedDestinationController::class);

    // Platform Admin Log Viewer
    Route::prefix('admin/logs')->name('admin.logs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\LogViewerController::class, 'index'])->name('index');
        Route::get('freeswitch', [\App\Http\Controllers\Api\LogViewerController::class, 'freeswitch'])->name('freeswitch');
        Route::get('application', [\App\Http\Controllers\Api\LogViewerController::class, 'application'])->name('application');
    });

    // Platform Admin SIP Status Monitor
    Route::prefix('admin/sip-status')->name('admin.sip-status.')->group(function () {
        Route::get('profiles', [\App\Http\Controllers\Api\SipStatusController::class, 'profiles'])->name('profiles');
        Route::get('profiles/detail', [\App\Http\Controllers\Api\SipStatusController::class, 'profileDetail'])->name('profiles.detail');
        Route::get('gateways', [\App\Http\Controllers\Api\SipStatusController::class, 'gateways'])->name('gateways');
        Route::get('registrations', [\App\Http\Controllers\Api\SipStatusController::class, 'registrations'])->name('registrations');
        Route::post('profiles/reload', [\App\Http\Controllers\Api\SipStatusController::class, 'reloadProfile'])->name('profiles.reload');
        Route::post('profiles/start', [\App\Http\Controllers\Api\SipStatusController::class, 'startProfile'])->name('profiles.start');
        Route::post('profiles/stop', [\App\Http\Controllers\Api\SipStatusController::class, 'stopProfile'])->name('profiles.stop');
        Route::post('registrations/kill', [\App\Http\Controllers\Api\SipStatusController::class, 'killRegistration'])->name('registrations.kill');
        Route::post('gateways/kill', [\App\Http\Controllers\Api\SipStatusController::class, 'killGateway'])->name('gateways.kill');
    });

    Route::prefix('tenants/{tenant}')->middleware('tenant.access')->group(function () {
        Route::get('stats', TenantStatsController::class)->name('tenants.stats');

        // Usage metering
        Route::get('usage/summary', [UsageController::class, 'summary'])->name('tenants.usage.summary');
        Route::post('usage/collect', [UsageController::class, 'collect'])->name('tenants.usage.collect');
        Route::get('usage/reconcile', [UsageController::class, 'reconcile'])->name('tenants.usage.reconcile');

        // Core resources
        Route::apiResource('extensions', ExtensionController::class);
        Route::get('extensions/{extension}/webrtc-config', [ExtensionController::class, 'webRtcConfig'])->name('extensions.webrtc-config');

        // System media (audio prompts, MOH)
        Route::apiResource('system-media', SystemMediaController::class)->parameters(['system-media' => 'mediaId']);

        // Real-time SIP registration status
        Route::get('extensions/status/all', [RegistrationStatusController::class, 'bulkExtensionStatus'])->name('extensions.status.all');
        Route::get('extensions/{extension}/status', [RegistrationStatusController::class, 'extensionStatus'])->name('extensions.status');
        Route::get('gateways/{gateway}/status', [RegistrationStatusController::class, 'gatewayStatus'])->name('gateways.status');
        Route::apiResource('holiday-calendars', HolidayCalendarController::class);
        Route::apiResource('schedules', ScheduleController::class);
        Route::apiResource('teams', TeamController::class);
        Route::apiResource('flows', FlowController::class);
        Route::post('flows/{flow}/publish', [FlowController::class, 'publish'])->name('flows.publish');

        Route::apiResource('dids', DidController::class);
        Route::apiResource('ring-groups', RingGroupController::class);
        Route::apiResource('ivrs', IvrController::class);
        Route::apiResource('time-conditions', TimeConditionController::class);
        Route::apiResource('webhooks', WebhookController::class);
        Route::get('webhooks/{webhook}/delivery-attempts', [WebhookController::class, 'deliveryAttempts'])->name('webhooks.delivery-attempts');
        Route::get('webhooks/{webhook}/delivery-stats', [WebhookController::class, 'deliveryStats'])->name('webhooks.delivery-stats');
        Route::apiResource('device-profiles', DeviceProfileController::class);
        Route::post('mobile-devices/register', [MobileDeviceController::class, 'register'])->name('mobile-devices.register');
        Route::put('mobile-devices/{endpointBinding}', [MobileDeviceController::class, 'update'])->name('mobile-devices.update');
        Route::delete('mobile-devices/{endpointBinding}', [MobileDeviceController::class, 'destroy'])->name('mobile-devices.destroy');
        Route::post('mobile-devices/{endpointBinding}/refresh-token', [MobileDeviceController::class, 'refreshToken'])->name('mobile-devices.refresh-token');
        Route::post('mobile-devices/{endpointBinding}/heartbeat', [MobileDeviceController::class, 'heartbeat'])->name('mobile-devices.heartbeat');
        Route::post('mobile-devices/{endpointBinding}/capabilities', [MobileDeviceController::class, 'capabilities'])->name('mobile-devices.capabilities');
        Route::apiResource('gateways', GatewayController::class);
        Route::apiResource('bridges', BridgeController::class);
        Route::apiResource('agents', AgentController::class);
        Route::post('agents/{agent}/state', [AgentController::class, 'changeState'])->name('agents.state');
        Route::apiResource('queues', QueueController::class);
        Route::get('queues/{queue}/members', [QueueController::class, 'members'])->name('queues.members');
        Route::post('queues/{queue}/members', [QueueController::class, 'addMember'])->name('queues.members.store');
        Route::delete('queues/{queue}/members/{agent}', [QueueController::class, 'removeMember'])->name('queues.members.destroy');
        Route::get('queues/{queue}/metrics/realtime', [QueueMetricsController::class, 'realtime'])->name('queues.metrics.realtime');
        Route::post('queues/{queue}/metrics/aggregate', [QueueMetricsController::class, 'aggregate'])->name('queues.metrics.aggregate');
        Route::get('queues/{queue}/metrics/history', [QueueMetricsController::class, 'history'])->name('queues.metrics.history');
        Route::get('wallboard', [QueueMetricsController::class, 'wallboard'])->name('wallboard');
        Route::get('agent-states', [QueueMetricsController::class, 'agentStates'])->name('agent-states');
        Route::get('codec-metrics', [CodecMetricsController::class, 'index'])->name('codec-metrics.index');
        Route::apiResource('recordings', RecordingController::class)->only(['index', 'show', 'destroy']);
        Route::get('recordings/{recording}/download', [RecordingController::class, 'download'])->name('recordings.download');
        Route::get('call-events', [CallEventController::class, 'index'])->name('call-events.index');
        Route::get('call-events/stream', [CallEventStreamController::class, 'stream'])->name('call-events.stream');
        Route::get('call-events/{callUuid}/trace', [CallEventController::class, 'trace'])->name('call-events.trace');
        Route::get('call-events/replay/{eventId}', [CallEventController::class, 'replay'])->name('call-events.replay');
        Route::post('call-events/redispatch/{eventId}', [CallEventController::class, 'redispatch'])->name('call-events.redispatch');

        // Call sessions & traces
        Route::get('calls', [CallSessionController::class, 'index'])->name('calls.index');
        Route::get('calls/{callSession}', [CallSessionController::class, 'show'])->name('calls.show');
        Route::get('calls/{callSession}/analyze', [CallSessionController::class, 'analyze'])->name('calls.analyze');
        Route::post('calls/originate', [CallController::class, 'originate'])->name('calls.originate');
        Route::get('calls/status', [CallController::class, 'status'])->name('calls.status');
        Route::post('calls/hangup', [CallController::class, 'hangup'])->name('calls.hangup');
        Route::post('calls/transfer', [CallController::class, 'transfer'])->name('calls.transfer');
        Route::post('calls/recording', [CallController::class, 'toggleRecording'])->name('calls.recording');
        Route::post('calls/hold', [CallController::class, 'hold'])->name('calls.hold');

        Route::get('cdrs/export', [CdrExportController::class, 'export'])->name('cdrs.export');
        Route::post('cdrs/export', [CdrExportController::class, 'export'])->name('cdrs.export.post');
        Route::apiResource('cdrs', CallDetailRecordController::class)->only(['index', 'show']);

        // CDR Analytics
        Route::prefix('cdrs/analytics')->name('cdrs.analytics.')->group(function () {
            Route::get('summary', [CdrAnalyticsController::class, 'summary'])->name('summary');
            Route::get('volume', [CdrAnalyticsController::class, 'volume'])->name('volume');
            Route::get('quality', [CdrAnalyticsController::class, 'quality'])->name('quality');
            Route::get('destinations', [CdrAnalyticsController::class, 'destinations'])->name('destinations');
        });

        Route::apiResource('call-routing-policies', CallRoutingPolicyController::class);
        Route::post('call-routing-policies/{call_routing_policy}/evaluate', [CallRoutingPolicyController::class, 'evaluate'])
            ->name('call-routing-policies.evaluate');

        // Audit logs (read-only)
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });
});
