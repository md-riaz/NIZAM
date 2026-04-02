<?php

return [
    'name' => env('APP_NAME', 'Communications Platform'),
    'slug' => 'communications-platform',

    'ui' => [
        'theme_storage_key' => 'communications-platform-theme',
    ],

    'webhooks' => [
        'signature_header_prefix' => 'X-Communications-Platform',
    ],

    'docker' => [
        'certbot_container' => 'communications-platform-certbot',
    ],
];
