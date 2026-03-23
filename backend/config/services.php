<?php

return [

    'mailgun' => ['domain' => env('MAILGUN_DOMAIN'), 'secret' => env('MAILGUN_SECRET'), 'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net')],
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],

    'meta' => [
        'app_id'       => env('META_APP_ID'),
        'app_secret'   => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        // Instagram uses the same Meta credentials
        'client_id'    => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
    ],

    'instagram' => [
        'client_id'    => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
    ],

    'tiktok' => [
        'client_key'    => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri'  => env('TIKTOK_REDIRECT_URI'),
        'client_id'     => env('TIKTOK_CLIENT_KEY'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_REDIRECT_URI'),
    ],

    'youtube' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => env('GOOGLE_REDIRECT_URI'),
    ],

    'snapchat' => [
        'client_id'     => env('SNAPCHAT_CLIENT_ID'),
        'client_secret' => env('SNAPCHAT_CLIENT_SECRET'),
        'redirect_uri'  => env('SNAPCHAT_REDIRECT_URI'),
    ],

    'pinterest' => [
        'app_id'        => env('PINTEREST_APP_ID'),
        'app_secret'    => env('PINTEREST_APP_SECRET'),
        'redirect_uri'  => env('PINTEREST_REDIRECT_URI'),
        'client_id'     => env('PINTEREST_APP_ID'),
        'client_secret' => env('PINTEREST_APP_SECRET'),
    ],

];
