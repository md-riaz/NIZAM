<?php

return [
    'freeswitch' => [
        'host' => env('FREESWITCH_HOST', '127.0.0.1'),
        'esl_port' => (int) env('FREESWITCH_ESL_PORT', 8021),
        'esl_password' => env('FREESWITCH_ESL_PASSWORD', 'ClueCon'),
        'xml_curl_url' => env('FREESWITCH_XML_CURL_URL', '/freeswitch/xml-curl'),
        'log_path' => env('FREESWITCH_LOG_PATH', '/var/log/freeswitch/freeswitch.log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flow Runtime Mode
    |--------------------------------------------------------------------------
    |
    | Controls which runtime engine executes published flows.
    | - "interpreted": PHP-based FlowExecutionService (v0, legacy)
    | - "compiled": FreeSWITCH dialplan + Lua helpers (v1, target)
    |
    | Can be overridden per-flow or per-tenant via flow_version.runtime_mode.
    | Global default here allows gradual migration.
    |
    */
    'flow_runtime_mode' => env('FLOW_RUNTIME_MODE', 'interpreted'),

    /*
    |--------------------------------------------------------------------------
    | Interpreted Mode Deprecation
    |--------------------------------------------------------------------------
    |
    | When enabled, interpreted mode triggers a warning and logs usage.
    | This flag allows gradual migration from interpreted to compiled runtime.
    | Once compiled runtime is stable, interpreted mode can be fully removed.
    |
    */
    'interpreted_mode_deprecated' => env('INTERPRETED_MODE_DEPRECATED', false),

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
        'rtp_ip' => env('RTP_IP', 'auto'),
        'sip_ip' => env('SIP_IP', 'auto'),
        'ext_rtp_ip' => env('EXT_RTP_IP', 'auto-nat'),
        'ext_sip_ip' => env('EXT_SIP_IP', 'auto-nat'),
        'aggressive_nat_detection' => env('AGGRESSIVE_NAT_DETECTION', false),
        'local_network_acl' => env('LOCAL_NETWORK_ACL', 'localnet.auto'),
        'dtmf_type' => env('DTMF_TYPE', 'rfc2833'),
        'srtp_policy' => env('SRTP_POLICY', 'optional'),
        'outbound_caller_id_pai' => env('OUTBOUND_CALLER_ID_PAI', false),
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
        'patterns' => ['911', '112', '999', '000', '110', '119'],
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

    /*
    |--------------------------------------------------------------------------
    | WebRTC Configuration (System-Wide)
    |--------------------------------------------------------------------------
    |
    | WebRTC settings are configured at the application level (like FusionPBX
    | super admin settings). All tenants share the same WebRTC infrastructure.
    |
    | STUN servers help WebRTC clients discover their public IP addresses.
    | TURN servers relay media when direct peer-to-peer connection fails.
    |
    */
    'webrtc' => [
        'enabled' => env('WEBRTC_ENABLED', false),
        'wss_port' => (int) env('WEBRTC_WSS_PORT', 7443),
        'stun_server' => env('WEBRTC_STUN_SERVER', 'stun:stun.l.google.com:19302'),
        'turn_server' => env('WEBRTC_TURN_SERVER', null),
        'turn_username' => env('WEBRTC_TURN_USERNAME', null),
        'turn_password' => env('WEBRTC_TURN_PASSWORD', null),
        'codec_prefs' => env('WEBRTC_CODEC_PREFS', 'OPUS,PCMU,PCMA,G722'),
        'dtls_cert_dir' => env('WEBRTC_DTLS_CERT_DIR', '/usr/local/freeswitch/certs'),
    ],

    'gateway_provisioning' => [
        'profile' => env('FREESWITCH_GATEWAY_PROFILE', 'external'),
        'external_directory' => env('FREESWITCH_GATEWAY_DIRECTORY', storage_path('app/freeswitch/sip_profiles/external')),
    ],

    'call_delivery' => [
        'wake_window_seconds' => (int) env('CALL_DELIVERY_WAKE_WINDOW_SECONDS', 30),
        'pstn_delay_seconds' => (int) env('CALL_DELIVERY_PSTN_DELAY_SECONDS', 8),
    ],
];
