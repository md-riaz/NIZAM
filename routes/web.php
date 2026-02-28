<?php

use App\Http\Controllers\FreeswitchXmlController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/freeswitch/xml-curl', [FreeswitchXmlController::class, 'handle'])
    ->name('freeswitch.xml-curl');

Route::get('/provision/{macAddress}', [\App\Http\Controllers\ProvisioningController::class, 'provision'])
    ->name('provision')
    ->where('macAddress', '[a-fA-F0-9:.\-]+');
