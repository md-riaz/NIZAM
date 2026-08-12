<?php

return [
    'freeswitch' => [
        'host' => env('FREESWITCH_HOST', '127.0.0.1'),
        'esl_port' => (int) env('FREESWITCH_ESL_PORT', 8021),
        'esl_password' => env('FREESWITCH_ESL_PASSWORD', 'ClueCon'),
        // Connect and read timeout for the event socket. Bounds how long a
        // web request can wait on an unresponsive switch.
        'esl_timeout' => (int) env('FREESWITCH_ESL_TIMEOUT', 10),
        'xml_curl_url' => env('FREESWITCH_XML_CURL_URL', '/freeswitch/xml-curl'),
        'log_path' => env('FREESWITCH_LOG_PATH', '/var/log/freeswitch/freeswitch.log'),
        'sip_port' => env('FREESWITCH_SIP_PORT'),
        'external_sip_port' => env('FREESWITCH_EXTERNAL_SIP_PORT'),
        'wss_port' => env('FREESWITCH_WSS_PORT'),
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
    | Can be overridden per-flow or per-organization via flow_version.runtime_mode.
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
    | Activation state for nwidart modules is managed exclusively by
    | nwidart/laravel-modules via modules_statuses.json. Use:
    |
    |   php artisan module:enable  PbxRouting
    |   php artisan module:disable PbxRouting
    |
    | Built-in app-local modules are configured separately below with explicit
    | enabled flags because they live inside app/Modules instead of nwidart.
    |
    | Then restart the application process for the change to take effect.
    | Core functionality (organizations, auth, extensions, event bus, dialplan
    | compiler, policy engine, FreeSWITCH adapter) is always active.
    |
    */
    'app_local_modules' => [
        'voicemail' => [
            'enabled' => env('APP_LOCAL_MODULE_VOICEMAIL_ENABLED', true),
        ],
        'media-archive' => [
            'enabled' => env('APP_LOCAL_MODULE_MEDIA_ARCHIVE_ENABLED', true),
        ],
        'messaging' => [
            'enabled' => env('APP_LOCAL_MODULE_MESSAGING_ENABLED', true),
        ],
    ],

    'bootstrap' => [
        'default_timezone' => env('BOOTSTRAP_DEFAULT_TIMEZONE', 'Asia/Dhaka'),
        'default_country' => env('BOOTSTRAP_DEFAULT_COUNTRY', 'Bangladesh'),
        'business_hours' => [
            'start' => env('BOOTSTRAP_BUSINESS_HOURS_START', '09:00'),
            'end' => env('BOOTSTRAP_BUSINESS_HOURS_END', '17:00'),
            'days' => [1, 2, 3, 4, 5],
        ],
        'service_codes' => [
            'voicemail_main' => '*98',
            'send_to_voicemail_prefix' => '*99',
            'intercom_prefix' => '*8',
            'paging_prefix' => '*80',
            'dnd_on' => '*78',
            'dnd_off' => '*79',
            'call_forward_activate' => '*72',
            'call_forward_disable' => '*73',
            'call_forward_restore' => '*74',
            'call_return' => '*69',
            'operator' => '0',
            'pickup_direct_prefix' => '**',
            'pickup_group' => '*8',
            'park_auto' => '*5900',
        ],
        'parking' => [
            'orbit_start' => 5901,
            'orbit_end' => 5999,
            'timeout' => 900,
            'lot' => 'park',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WebRTC Configuration (System-Wide)
    |--------------------------------------------------------------------------
    |
    | WebRTC settings are configured at the application level (like FusionPBX
    | super admin settings). All organizations share the same WebRTC infrastructure.
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

    'sip_profile_provisioning' => [
        // Where compiled SIP profile XML is written for FreeSWITCH to read.
        // Overridable so the test suite does not rewrite the checked-in
        // profiles under storage/app — saving any SipProfile recompiles every
        // profile to disk, which otherwise leaves the working tree dirty.
        'directory' => env('FREESWITCH_SIP_PROFILE_DIRECTORY', storage_path('app/freeswitch/sip_profiles')),
    ],

    'xml_cdr' => [
        'enabled' => env('FREESWITCH_XML_CDR_ENABLED', false),
        'directory' => env('FREESWITCH_XML_CDR_DIRECTORY', '/var/log/freeswitch/xml_cdr'),
        'log_dir' => env('FREESWITCH_XML_CDR_LOG_DIR', env('FREESWITCH_XML_CDR_DIRECTORY', '/var/log/freeswitch/xml_cdr')),
        'watcher' => env('FREESWITCH_XML_CDR_WATCHER', 'inotify'),
        'poll_interval_seconds' => (int) env('FREESWITCH_XML_CDR_POLL_INTERVAL', 5),
        'cleanup_on_success' => env('FREESWITCH_XML_CDR_CLEANUP_ON_SUCCESS', true),
        'cleanup_after_ingest' => env('FREESWITCH_XML_CDR_CLEANUP_AFTER_INGEST', env('FREESWITCH_XML_CDR_CLEANUP_ON_SUCCESS', true)),
    ],

    'call_delivery' => [
        'wake_window_seconds' => (int) env('CALL_DELIVERY_WAKE_WINDOW_SECONDS', 30),
        'pstn_delay_seconds' => (int) env('CALL_DELIVERY_PSTN_DELAY_SECONDS', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notification Driver
    |--------------------------------------------------------------------------
    |
    | Controls which transport is used to deliver VoIP / data push notifications
    | to mobile devices during call delivery orchestration.
    |
    | Supported drivers:
    |   - "log"  — logs the notification payload (default, safe for development)
    |
    | To enable live push delivery, set PUSH_DRIVER to a custom driver that
    | implements APNs VoIP (for iOS CallKit) and FCM (for Android) delivery.
    |
    */
    'push_driver' => env('PUSH_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Push Notification Drivers
    |--------------------------------------------------------------------------
    */
    'push' => [
        'apns' => [
            'key_id' => env('APNS_KEY_ID'),
            'team_id' => env('APNS_TEAM_ID'),
            'private_key' => env('APNS_PRIVATE_KEY'),
            'private_key_path' => env('APNS_PRIVATE_KEY_PATH'),
            'bundle_id' => env('APNS_BUNDLE_ID'),
            'production' => (bool) env('APNS_PRODUCTION', true),
        ],
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
            'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
        ],
    ],
];
