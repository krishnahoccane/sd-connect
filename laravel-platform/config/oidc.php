<?php

return [
    'issuer' => env('OIDC_ISSUER', 'https://connect.twsd.me'),
    'id_token_ttl_seconds' => (int) env('OIDC_ID_TOKEN_TTL_SECONDS', 3600),
    'key_rotation_days' => (int) env('OIDC_KEY_ROTATION_DAYS', 90),
    'active_kid' => env('OIDC_ACTIVE_KEY_ID'),

    'discovery' => [
        'scopes_supported' => [
            'openid',
            'profile',
            'email',
            'phone',
            'offline_access',
        ],
        'response_types_supported' => ['code'],
        'response_modes_supported' => ['query', 'form_post'],
        'grant_types_supported' => [
            'authorization_code',
            'refresh_token',
        ],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'token_endpoint_auth_methods_supported' => [
            'client_secret_basic',
            'client_secret_post',
            'none',
        ],
        'code_challenge_methods_supported' => ['S256'],
        'claims_supported' => [
            'sub',
            'iss',
            'aud',
            'exp',
            'iat',
            'auth_time',
            'nonce',
            'name',
            'given_name',
            'family_name',
            'email',
            'email_verified',
            'phone_number',
            'phone_number_verified',
            'org_id',
            'org_slug',
            'org_roles',
            'amr',
        ],
    ],
];
