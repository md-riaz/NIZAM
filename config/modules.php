<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NIZAM Modules
    |--------------------------------------------------------------------------
    |
    | Each module can be enabled or disabled independently. Disabled modules
    | will not register routes, event listeners, permissions, or dialplan
    | contributions. Core functionality (tenants, auth, extensions, event bus,
    | dialplan compiler, policy engine, FreeSWITCH adapter) is always active.
    |
    */

    'pbx-routing' => [
        'class' => \App\Modules\PbxRoutingModule::class,
        'enabled' => env('MODULE_PBX_ROUTING', true),
    ],

    'pbx-contact-center' => [
        'class' => \App\Modules\PbxContactCenterModule::class,
        'enabled' => env('MODULE_PBX_CONTACT_CENTER', true),
    ],

    'pbx-automation' => [
        'class' => \App\Modules\PbxAutomationModule::class,
        'enabled' => env('MODULE_PBX_AUTOMATION', true),
    ],

    'pbx-analytics' => [
        'class' => \App\Modules\PbxAnalyticsModule::class,
        'enabled' => env('MODULE_PBX_ANALYTICS', true),
    ],

    'pbx-provisioning' => [
        'class' => \App\Modules\PbxProvisioningModule::class,
        'enabled' => env('MODULE_PBX_PROVISIONING', true),
    ],

];
