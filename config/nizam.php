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
    | NizamModule implementations are discovered automatically at boot time by
    | scanning all nwidart-registered modules for a class matching the
    | conventional path Modules\{Name}\{Name}Module that implements NizamModule.
    |
    | Activation state (enabled/disabled) is managed exclusively by
    | nwidart/laravel-modules via modules_statuses.json. Use:
    |
    |   php artisan module:enable  PbxRouting
    |   php artisan module:disable PbxRouting
    |
    | Then restart the application process for the change to take effect.
    | Core functionality (tenants, auth, extensions, event bus, dialplan
    | compiler, policy engine, FreeSWITCH adapter) is always active.
    |
    */
];
