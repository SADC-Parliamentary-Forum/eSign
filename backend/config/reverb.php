<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    |
    | This option controls the default server configuration that will be used
    | by Reverb. You may define as many servers as needed, but this one
    | will be used by default when starting the server.
    |
    */

    'default' => 'reverb',

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    | Here you may define the configuration for each Reverb server. You may
    | define multiple servers to support various application scenarios
    | or to group your applications by their specific needs.
    |
    */

    'servers' => [

        'reverb' => [
            'host' => '0.0.0.0',
            'port' => 8080,
            'hostname' => env('REVERB_HOST', 'localhost'),
            'options' => [
                'tls' => [],
            ],
            'scaling' => [
                'enabled' => false,
                'channel' => 'reverb_scaling',
                'server' => [
                    'url' => env('REDIS_URL'),
                ],
            ],
            'pulse_ingest_interval' => 15,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    |
    | Here you may define how Reverb applications are managed. You may choose
    | to use the "config" provider to define applications in this file or
    | create your own provider to load applications from a database.
    |
    */

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'options' => [
                    'host' => env('REVERB_HOST'),
                    'port' => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                'allowed_origins' => ['*'],
                'ping_interval' => 60,
                'max_message_size' => 10_000,
            ],
        ],

    ],

];
