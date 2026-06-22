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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        // CLAUDE.md env uses RESEND_API_KEY; fall back to the framework default.
        'key' => env('RESEND_API_KEY', env('RESEND_KEY')),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        // ap-southeast-2 is served by the US API host; EU domains use api.eu.
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        // The From address used by the notification email providers.
        'from' => env('MAIL_FROM_ADDRESS', 'no-reply@dvaro.com.au'),
    ],

    'clicksend' => [
        'username' => env('CLICKSEND_USERNAME'),
        'api_key' => env('CLICKSEND_API_KEY'),
        // WhatsApp sends are billed against a registered ClickSend number.
        'whatsapp_number' => env('CLICKSEND_WHATSAPP_NUMBER'),
    ],

    'cellcast' => [
        'api_key' => env('CELLCAST_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
