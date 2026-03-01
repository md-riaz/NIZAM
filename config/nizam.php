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
    | Media & NAT Configuration
    |--------------------------------------------------------------------------
    |
    | These settings document the expected FreeSWITCH media posture. They are
    | consumed by the dialplan compiler and provisioning templates. Actual
    | FreeSWITCH SIP profile settings must match these values.
    |
    */
    'media' => [
        'rtp_port_range_start' => (int) env('RTP_PORT_RANGE_START', 16384),
        'rtp_port_range_end' => (int) env('RTP_PORT_RANGE_END', 32768),
        'ext_rtp_ip' => env('EXT_RTP_IP', 'auto-nat'),
        'ext_sip_ip' => env('EXT_SIP_IP', 'auto-nat'),
        'dtmf_type' => env('DTMF_TYPE', 'rfc2833'),
        'srtp_policy' => env('SRTP_POLICY', 'optional'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Number Patterns
    |--------------------------------------------------------------------------
    |
    | NIZAM does not support emergency calling in v1.0. These patterns are
    | provided so operators can implement blocking rules in custom dialplan
    | or SBC configurations. See docs/KNOWN_LIMITATIONS.md for details.
    |
    */
    'emergency' => [
        'patterns' => ['911', '933', '112', '999', '000', '110', '119'],
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
