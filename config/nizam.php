<?php

return [
    'freeswitch' => [
        'host' => env('FREESWITCH_HOST', '127.0.0.1'),
        'esl_port' => (int) env('FREESWITCH_ESL_PORT', 8021),
        'esl_password' => env('FREESWITCH_ESL_PASSWORD', 'ClueCon'),
        'xml_curl_url' => env('FREESWITCH_XML_CURL_URL', '/freeswitch/xml-curl'),
    ],

    /*
    |--------------------------------------------------------------------------
    | NIZAM Module Registry (Telecom Hooks)
    |--------------------------------------------------------------------------
    |
    | Each module can be enabled or disabled independently. Disabled modules
    | will not register routes, event listeners, permissions, or dialplan
    | contributions. Core functionality (tenants, auth, extensions, event bus,
    | dialplan compiler, policy engine, FreeSWITCH adapter) is always active.
    |
    | The 'class' references the NizamModule implementation that provides
    | telecom-specific hooks (dialplan, policy, events, permissions).
    |
    | Module lifecycle (enable/disable, discovery) is managed by
    | nwidart/laravel-modules. This config bridges those modules with
    | NIZAM's telecom hook registry.
    |
    */
    'modules' => [
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
    ],
];
