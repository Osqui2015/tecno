<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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
    | Scrapers
    |--------------------------------------------------------------------------
    |
    | Proxy opcional para los scrapers (por si el proveedor bloquea la IP).
    | Se puede definir uno global con SCRAPER_PROXY o uno por proveedor con
    | DAZ_SCRAPER_PROXY / TUC_SCRAPER_PROXY / TERO_SCRAPER_PROXY.
    |
    | Formato: http://usuario:clave@host:puerto
    |
    */

    'scraper' => [
        'proxy' => env('SCRAPER_PROXY'),
        'daz'   => env('DAZ_SCRAPER_PROXY'),
        'tuc'   => env('TUC_SCRAPER_PROXY'),
        'tero'  => env('TERO_SCRAPER_PROXY'),
    ],

];
