<?php

return [
    'name' => env('APP_NAME', 'Communications Platform'),
    'slug' => 'platform',

    'ui' => [
        'theme_storage_key' => 'platform-theme',
    ],

    'webhooks' => [
        'signature_header_prefix' => 'X-Platform',
    ],

    'docker' => [
        'certbot_container' => 'certbot',
    ],
];
