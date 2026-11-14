<?php

return [
    'paths' => ['api/v1/*', 'oauth/*', '.well-known/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://connect.twsd.me'))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
