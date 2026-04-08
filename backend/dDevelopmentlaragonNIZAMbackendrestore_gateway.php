<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\App\Models\Gateway::withoutEvents(function () {
    $tenant = \App\Models\Tenant::first();
    
    $gateway = new \App\Models\Gateway();
    $gateway->id = '019d6263-955e-71b0-b26a-aca27ede6c0c'; // Extract from v_019d6263-955e-71b0-b26a-aca27ede6c0c
    $gateway->tenant_id = $tenant ? $tenant->id : null;
    $gateway->name = 'external-trunk-09644196197';
    $gateway->host = '123.0.31.250';
    $gateway->port = 5060;
    $gateway->username = '09644196197';
    $gateway->password = 'olkmnbvv@13wsxA';
    $gateway->realm = '123.0.31.250:5060';
    $gateway->proxy = '123.0.31.250:5060';
    $gateway->register_proxy = '123.0.31.250:5060';
    $gateway->from_domain = '123.0.31.250:5060';
    $gateway->extension = '09644196197';
    $gateway->inbound_codecs = ['PCMA', 'PCMU', 'G729'];
    $gateway->outbound_codecs = ['PCMA', 'PCMU', 'G729'];
    $gateway->allow_transcoding = false;
    $gateway->expire_seconds = 3600;
    $gateway->retry_seconds = 30;
    $gateway->caller_id_in_from = true;
    $gateway->profile = 'external';
    $gateway->transport = 'udp';
    $gateway->register = true;
    $gateway->is_active = true;
    $gateway->save();
});

echo "Gateway restored.\n";
