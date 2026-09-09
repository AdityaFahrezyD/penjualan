<?php

return [
    'rate_limits' => [
        'login_identity_per_minute' => (int) env('API_LOGIN_IDENTITY_PER_MINUTE', 5),
        'login_ip_per_minute' => (int) env('API_LOGIN_IP_PER_MINUTE', 30),
        'authenticated_per_minute' => (int) env('API_AUTHENTICATED_PER_MINUTE', 120),
    ],

    'response_cache' => [
        'enabled' => env('API_RESPONSE_CACHE_ENABLED', true),
        'ttl_seconds' => (int) env('API_RESPONSE_CACHE_TTL_SECONDS', 60),
    ],
];
