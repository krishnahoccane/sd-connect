<?php

return [
    'issuer' => env('OIDC_ISSUER', 'https://connect.twsd.me'),

    'password' => [
        'driver' => 'argon2id',
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_symbol' => true,
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
    ],

    'otp' => [
        'digits' => 6,
        'expiry_seconds' => 300,
        'resend_cooldown_seconds' => 60,
        'max_verification_attempts' => 5,
        'max_resends_per_window' => 3,
        'resend_window_minutes' => 15,
    ],

    'oauth' => [
        'require_pkce_s256' => true,
        'allow_wildcard_redirect_uris' => false,
        'disabled_grant_types' => ['implicit', 'password'],
        'access_token_ttl_minutes' => (int) env('PASSPORT_ACCESS_TOKEN_TTL_MINUTES', 15),
        'refresh_token_ttl_days' => (int) env('PASSPORT_REFRESH_TOKEN_TTL_DAYS', 30),
        'auth_code_ttl_minutes' => (int) env('PASSPORT_AUTH_CODE_TTL_MINUTES', 5),
        'rotate_refresh_tokens' => true,
        'detect_refresh_token_reuse' => true,
    ],

    'providers' => [
        'whatsapp' => [
            'endpoint' => env('WHATSAPP_API_URL', 'https://api.whatsapp.webglit.com/api/create-message'),
            'appkey' => env('WHATSAPP_APPKEY', env('WHATSAPP_APP_KEY')),
            'authkey' => env('WHATSAPP_AUTHKEY', env('WHATSAPP_AUTH_KEY')),
            'timeout_seconds' => (int) env('WHATSAPP_TIMEOUT_SECONDS', 30),
        ],
    ],
];
