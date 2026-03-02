<?php

use App\Http\Controllers\FreeswitchXmlController;
use App\Http\Controllers\Web\UiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/login', 'welcome')->name('login');

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
});
