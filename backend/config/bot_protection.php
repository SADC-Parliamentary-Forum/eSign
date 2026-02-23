<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot Protection Status
    |--------------------------------------------------------------------------
    |
    | Enable or disable the entire bot protection system.
    |
    */
    'enabled' => env('BOT_PROTECTION_ENABLED', !in_array(env('APP_ENV'), ['local', 'testing'])),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Supported: "recaptcha", "hcaptcha", "internal"
    |
    */
    'provider' => env('BOT_PROTECTION_PROVIDER', 'recaptcha'),

    'providers' => [
        'recaptcha' => [
            'secret_key' => env('RECAPTCHA_SECRET_KEY'),
            'site_key' => env('RECAPTCHA_SITE_KEY'),
            'verify_url' => 'https://www.google.com/recaptcha/api/siteverify',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Thresholds
    |--------------------------------------------------------------------------
    |
    | Minimum score required (0.0 - 1.0) if not specified per action.
    |
    */
    'min_score' => 0.6,

    /*
    |--------------------------------------------------------------------------
    | Enforcement Settings
    |--------------------------------------------------------------------------
    */
    'enforcement' => [
        'block_on_failure' => true,
        'log_attempts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Action-Level Rules
    |--------------------------------------------------------------------------
    |
    | Define specific thresholds for high-risk actions.
    |
    */
    'actions' => [
        'login' => [
            'enabled' => true,
            'required_score' => 0.6,
        ],
        'register' => [
            'enabled' => true,
            'required_score' => 0.7,
        ],
        'sign_document' => [
            'enabled' => true,
            'required_score' => 0.7,
        ],
        'bulk_sign' => [
            'enabled' => true,
            'required_score' => 0.9,
        ],
        'document_upload' => [
            'enabled' => false, // Authenticated uploads only; skip reCAPTCHA to avoid blocking real users (VPN, privacy tools)
            'required_score' => 0.3,
        ],
    ],
];
