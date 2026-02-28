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
    | Maps NIZAM telecom module aliases to their NizamModule implementation
    | classes. Each entry bridges a nwidart-discovered module with NIZAM's
    | hook registry (dialplan, policy, events, permissions).
    |
    | Activation state (enabled/disabled) is managed exclusively by
    | nwidart/laravel-modules via modules_statuses.json. Use:
    |
    |   php artisan module:enable  PbxRouting
    |   php artisan module:disable PbxRouting
    |
    | Core functionality (tenants, auth, extensions, event bus, dialplan
    | compiler, policy engine, FreeSWITCH adapter) is always active.
    |
    */
    'modules' => [
        'pbx-routing' => [
            'class' => \Modules\PbxRouting\PbxRoutingModule::class,
        ],

        'pbx-contact-center' => [
            'class' => \Modules\PbxContactCenter\PbxContactCenterModule::class,
        ],

        'pbx-automation' => [
            'class' => \Modules\PbxAutomation\PbxAutomationModule::class,
        ],

        'pbx-analytics' => [
            'class' => \Modules\PbxAnalytics\PbxAnalyticsModule::class,
        ],

        'pbx-provisioning' => [
            'class' => \Modules\PbxProvisioning\PbxProvisioningModule::class,
        ],
    ],
];
