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

    'postmark'  => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend'    => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses'       => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack'     => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bdcourier' => [
        'url'             => env('BDCOURIER_API_URL', 'https://api.bdcourier.com'),
        'token'           => env('BDCOURIER_API_TOKEN'),
        'check_endpoint'  => env('BDCOURIER_CHECK_ENDPOINT', '/courier-check'),
        'method'          => env('BDCOURIER_METHOD', 'POST'),
        'timeout'         => (int) env('BDCOURIER_TIMEOUT', 30),
        'connect_timeout' => (int) env('BDCOURIER_CONNECT_TIMEOUT', 10),
        'force_ipv4'      => filter_var(env('BDCOURIER_FORCE_IPV4', true), FILTER_VALIDATE_BOOL),
        'verify_ssl'      => filter_var(env('BDCOURIER_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    ],

];
