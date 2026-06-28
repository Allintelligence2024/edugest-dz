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
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'satim' => [
        'terminal_id' => env('SATIM_TERMINAL_ID'),
        'merchant_id' => env('SATIM_MERCHANT_ID'),
        'password'    => env('SATIM_PASSWORD'),
        'url'         => env('SATIM_URL', 'https://test.satim.dz/payment/rest'),
        'sandbox'     => env('SATIM_SANDBOX', true),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    'firebase' => [
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'openai' => [
        'key'     => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 15),
    ],

    'whatsapp' => [
        'api_token'    => env('WHATSAPP_API_TOKEN'),
        'phone_id'     => env('WHATSAPP_PHONE_ID'),
        'api_url'      => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'edugest_verify'),
    ],

];
