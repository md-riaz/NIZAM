<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$raw = <<<EOT
    Profile::Gateway-Name                               Data            State   Ping Time       IB Calls(F/T)   OB Calls(F/T)
=================================================================================================
external::v_019d6263-955e-71b0-b26a-aca27ede6c0c        sip:09644196197@123.0.31.250:5060       REGED    12.49  0/0     0/0
=================================================================================================
1 gateway: Inbound(Failed/Total): 0/0,Outbound(Failed/Total):0/0
EOT;

$c = app(\App\Http\Controllers\Api\SipStatusController::class);
$method = new ReflectionMethod(\App\Http\Controllers\Api\SipStatusController::class, 'parseGateways');
$gateways = $method->invokeArgs($c, [$raw]);
var_dump($gateways);
