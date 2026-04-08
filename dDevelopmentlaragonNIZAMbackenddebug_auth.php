<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@nizam.local')->first();
$provider = \Illuminate\Support\Facades\Auth::getProvider();
var_dump($provider->retrieveByCredentials(['email' => 'admin@nizam.local']) !== null);
var_dump($provider->validateCredentials($user, ['password' => 'password']));
