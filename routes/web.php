<?php

use App\Http\Controllers\FreeswitchXmlController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\UiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/freeswitch/xml-curl', [FreeswitchXmlController::class, 'handle'])
    ->name('freeswitch.xml-curl');

Route::get('/provision/{macAddress}', [\App\Http\Controllers\ProvisioningController::class, 'provision'])
    ->name('provision')
    ->where('macAddress', '[a-fA-F0-9:.\-]+');

Route::middleware('auth')->prefix('ui')->name('ui.')->group(function () {
    Route::get('/dashboard/{tenant?}', [UiController::class, 'dashboard'])->name('dashboard');
    Route::get('/system-health/{tenant?}', [UiController::class, 'systemHealth'])->name('health');

    Route::get('/tenants/{tenant}/extensions', [UiController::class, 'extensions'])
        ->middleware('tenant.access')
        ->name('extensions');
    Route::post('/tenants/{tenant}/extensions', [UiController::class, 'extensionStore'])
        ->middleware('tenant.access')
        ->name('extensions.store');
    Route::put('/tenants/{tenant}/extensions/{extension}', [UiController::class, 'extensionUpdate'])
        ->middleware('tenant.access')
        ->name('extensions.update');
    Route::delete('/tenants/{tenant}/extensions/{extension}', [UiController::class, 'extensionDestroy'])
        ->middleware('tenant.access')
        ->name('extensions.destroy');

    Route::get('/modules/{tenant?}', [UiController::class, 'modulePanel'])->name('modules');
    Route::post('/modules/{moduleName}/toggle', [UiController::class, 'moduleToggle'])->name('modules.toggle');

    Route::get('/routing/dids', [UiController::class, 'surfacePage'])->defaults('page', 'routing.dids')->name('routing.dids');
    Route::get('/routing/ring-groups', [UiController::class, 'surfacePage'])->defaults('page', 'routing.ring-groups')->name('routing.ring-groups');
    Route::get('/routing/ivr', [UiController::class, 'surfacePage'])->defaults('page', 'routing.ivr')->name('routing.ivr');
    Route::get('/routing/time-conditions', [UiController::class, 'surfacePage'])->defaults('page', 'routing.time-conditions')->name('routing.time-conditions');

    Route::get('/contact-center/queues', [UiController::class, 'surfacePage'])->defaults('page', 'contact-center.queues')->name('contact-center.queues');
    Route::get('/contact-center/agents', [UiController::class, 'surfacePage'])->defaults('page', 'contact-center.agents')->name('contact-center.agents');
    Route::get('/contact-center/wallboard', [UiController::class, 'surfacePage'])->defaults('page', 'contact-center.wallboard')->name('contact-center.wallboard');

    Route::get('/automation/webhooks', [UiController::class, 'surfacePage'])->defaults('page', 'automation.webhooks')->name('automation.webhooks');
    Route::get('/automation/event-log-viewer', [UiController::class, 'surfacePage'])->defaults('page', 'automation.event-log-viewer')->name('automation.event-log-viewer');
    Route::get('/automation/retry-management', [UiController::class, 'surfacePage'])->defaults('page', 'automation.retry-management')->name('automation.retry-management');

    Route::get('/analytics/recordings', [UiController::class, 'surfacePage'])->defaults('page', 'analytics.recordings')->name('analytics.recordings');
    Route::get('/analytics/sla-trends', [UiController::class, 'surfacePage'])->defaults('page', 'analytics.sla-trends')->name('analytics.sla-trends');
    Route::get('/analytics/call-volume', [UiController::class, 'surfacePage'])->defaults('page', 'analytics.call-volume')->name('analytics.call-volume');

    Route::get('/media-policy/gateways', [UiController::class, 'surfacePage'])->defaults('page', 'media-policy.gateways')->name('media-policy.gateways');
    Route::get('/media-policy/codec-policy', [UiController::class, 'surfacePage'])->defaults('page', 'media-policy.codec-policy')->name('media-policy.codec-policy');
    Route::get('/media-policy/transcoding-stats', [UiController::class, 'surfacePage'])->defaults('page', 'media-policy.transcoding-stats')->name('media-policy.transcoding-stats');

    Route::get('/admin/tenants', [UiController::class, 'surfacePage'])->defaults('page', 'admin.tenants')->name('admin.tenants');
    Route::get('/admin/node-health-per-fs', [UiController::class, 'surfacePage'])->defaults('page', 'admin.node-health-per-fs')->name('admin.node-health-per-fs');
    Route::get('/admin/fraud-alerts', [UiController::class, 'surfacePage'])->defaults('page', 'admin.fraud-alerts')->name('admin.fraud-alerts');
});
