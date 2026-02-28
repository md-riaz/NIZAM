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
        'class' => \Modules\PbxRouting\PbxRoutingModule::class,
        'enabled' => env('MODULE_PBX_ROUTING', true),
    ],

    'pbx-contact-center' => [
        'class' => \Modules\PbxContactCenter\PbxContactCenterModule::class,
        'enabled' => env('MODULE_PBX_CONTACT_CENTER', true),
    ],

    'pbx-automation' => [
        'class' => \Modules\PbxAutomation\PbxAutomationModule::class,
        'enabled' => env('MODULE_PBX_AUTOMATION', true),
    ],

    'pbx-analytics' => [
        'class' => \Modules\PbxAnalytics\PbxAnalyticsModule::class,
        'enabled' => env('MODULE_PBX_ANALYTICS', true),
    ],

    'pbx-provisioning' => [
        'class' => \Modules\PbxProvisioning\PbxProvisioningModule::class,
        'enabled' => env('MODULE_PBX_PROVISIONING', true),
    ],

];
