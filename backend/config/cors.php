<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://127.0.0.1:8000')))),

    // In production, set CORS_ALLOWED_ORIGINS to exact frontend origin(s), e.g. https://esign.sadcpf.org
    // Patterns are disabled in production so only explicit origins are allowed (credentials-safe).
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'production'
        ? []
        : [
            '#^https?://localhost(:\d+)?$#',
            '#^https?://127\.0\.0\.1(:\d+)?$#',
        ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => true,

];
