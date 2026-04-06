<?php

use App\Http\Controllers\FreeswitchXmlController;
use Illuminate\Support\Facades\Route;

// ─── FreeSWITCH & Provisioning (must remain outside SPA) ────
Route::post('/freeswitch/xml-curl', [FreeswitchXmlController::class, 'handle'])
    ->name('freeswitch.xml-curl');

Route::get('/provision/{macAddress}', [\App\Http\Controllers\ProvisioningController::class, 'provision'])
    ->name('provision')
    ->where('macAddress', '[a-fA-F0-9:.\-]+');

// ─── SPA Shell removed as this is now a pure API ───

