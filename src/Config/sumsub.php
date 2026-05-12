<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Sumsub Application Token
    |--------------------------------------------------------------------------
    | Your Sumsub App Token, found in the Sumsub dashboard under
    | Developer Tools > App Tokens.
    */
    'app_token' => env('SUMSUB_APP_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Sumsub Secret Key
    |--------------------------------------------------------------------------
    | Your Sumsub Secret Key, used to sign HMAC-SHA256 request signatures
    | and to verify incoming webhook payloads.
    */
    'secret_key' => env('SUMSUB_SECRET_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Sumsub Base URL
    |--------------------------------------------------------------------------
    | The Sumsub REST API base URL. Change to the sandbox URL during testing.
    | Production: https://api.sumsub.com
    | Sandbox:    https://api.sumsub.com (same host, test credentials apply)
    */
    'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Secret used to verify the X-App-Token header sent by Sumsub webhooks.
    | Configure this in Sumsub dashboard > Webhooks.
    */
    'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Level Name
    |--------------------------------------------------------------------------
    | The default KYC verification level name configured in your Sumsub account.
    */
    'default_level_name' => env('SUMSUB_DEFAULT_LEVEL', 'basic-kyc-level'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    | The URI where Sumsub will POST webhook events.
    | This route is registered automatically by the service provider.
    */
    'webhook_route' => env('SUMSUB_WEBHOOK_ROUTE', 'webhooks/sumsub'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    | The queue connection to use for processing webhook jobs.
    | Set to null to process webhooks synchronously.
    */
    'queue_connection' => env('SUMSUB_QUEUE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    | The queue name to use for ProcessSumsubWebhook jobs.
    */
    'queue_name' => env('SUMSUB_QUEUE_NAME', 'default'),
];
