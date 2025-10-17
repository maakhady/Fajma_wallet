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


    'orange_money' => [
    'merchant_id'   => env('ORANGE_MONEY_MERCHANT_ID'),
    'merchant_name' => env('ORANGE_MONEY_MERCHANT_NAME'),
    'api_key'       => env('ORANGE_MONEY_API_KEY'),
    'api_secret'    => env('ORANGE_MONEY_API_SECRET'),
    'environment'   => env('ORANGE_MONEY_ENVIRONMENT', 'sandbox'),
],


'wave' => [
    'enabled' => env('WAVE_ENABLED', false),
    'api_key' => env('WAVE_API_KEY'),
    'base' => env('WAVE_API_BASE', 'https://api.wave.com'),
    'success_url' => env('WAVE_SUCCESS_URL'),
    'error_url' => env('WAVE_ERROR_URL'),
    'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
    'currency' => env('WAVE_CURRENCY', 'XOF'), 
    'balance_enabled' => env('WAVE_BALANCE_ENABLED', false), 
],


];
