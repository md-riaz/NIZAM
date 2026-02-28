<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallDetailRecordController;
use App\Http\Controllers\Api\CallFlowController;
use App\Http\Controllers\Api\CallRoutingPolicyController;
use App\Http\Controllers\Api\ExtensionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\TenantStatsController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\UserController;
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

        // Core resources
        Route::apiResource('extensions', ExtensionController::class);
        Route::get('cdrs/export', [CallDetailRecordController::class, 'export'])->name('cdrs.export');
        Route::apiResource('cdrs', CallDetailRecordController::class)->only(['index', 'show']);

        Route::apiResource('call-routing-policies', CallRoutingPolicyController::class);
        Route::post('call-routing-policies/{call_routing_policy}/evaluate', [CallRoutingPolicyController::class, 'evaluate'])
            ->name('call-routing-policies.evaluate');
        Route::apiResource('call-flows', CallFlowController::class);

        // Audit logs (read-only)
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });
});
