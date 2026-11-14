<?php

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 480),
    'expire_on_close' => false,
    'encrypt' => (bool) env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'session'),
    'table' => 'sessions',
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'twsd_connect_session'),
    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => (bool) env('SESSION_HTTP_ONLY', true),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => false,
];
