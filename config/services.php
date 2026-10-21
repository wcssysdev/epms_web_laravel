<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HSE Portal
    |--------------------------------------------------------------------------
    |
    | External HSE (SHE) Portal REST API. The base URL is stored per-company in
    | m_company_config.hse_api_url; the API key is application/environment
    | configured (not a DB column) and read here via config('services.hse.api_key').
    |
    */
    'hse' => [
        'api_key' => env('HSE_PORTAL_API_KEY', 'Kyv61GnBqNNw0HGnoP/fr8Yme3IhT2oPgpDL2x53gXc='),
    ],

];
