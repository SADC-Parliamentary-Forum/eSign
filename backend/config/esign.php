<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Features
    |--------------------------------------------------------------------------
    |
    | Global feature flags for the application.
    |
    */
    'features' => [
        'bulk_signing' => env('FEATURE_BULK_SIGNING', true),
        'risk_scoring' => env('FEATURE_RISK_SCORING', true),
        'document_summary' => env('FEATURE_DOCUMENT_SUMMARY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow & Approval Rules
    |--------------------------------------------------------------------------
    |
    | Configuration for document workflows and approval thresholds.
    |
    */
    'workflow' => [
        'max_parties' => 10,
        'allow_self_sign' => true,
        'approval_thresholds' => [
            'low_risk_limit' => 5000,
            'medium_risk_limit' => 20000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Specific configuration for third party integrations not covered by services.php
    |
    */
    'services' => [
        'ipapi' => [
            'url' => env('IPAPI_URL', 'https://ipapi.co'),
        ],
    ],
];
