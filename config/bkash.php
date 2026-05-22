<?php

return [

    /*
    |--------------------------------------------------------------------------
    | bKash checkout (tokenized API, v1.2.0-beta)
    |--------------------------------------------------------------------------
    |
    | Sandbox base URL is used by default. Set BKASH_ENABLED=true and your
    | sandbox credentials from the bKash merchant developer portal.
    |
    */

    'enabled' => (bool) env('BKASH_ENABLED', false),

    /** tokenized_web | personal — personal = Send Money + TrxID + SMS verify (PipraPay-style) */
    'gateway_type' => env('BKASH_GATEWAY_TYPE', 'tokenized_web'),

    /** Personal bKash number (01XXXXXXXXX) when gateway_type=personal */
    'personal_number' => env('BKASH_PERSONAL_NUMBER'),

    'personal_name' => env('BKASH_PERSONAL_NAME', env('ISP_COMPANY_NAME', 'ISP')),

    'instructions' => env('BKASH_PERSONAL_INSTRUCTIONS'),

    /** sandbox | live — panel can override via app_settings */
    'environment' => env('BKASH_ENVIRONMENT', 'sandbox'),

    'base_url' => rtrim(env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'), '/'),

    'activation_date' => env('BKASH_ACTIVATION_DATE'),

    'expiry_date' => env('BKASH_EXPIRY_DATE'),

    /**
     * Where checkout is offered: admin, public_pay, portal, reseller
     *
     * @var list<string>
     */
    'channels' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BKASH_CHANNELS', 'admin,public_pay,portal,reseller'))
    ))),

    'app_key' => env('BKASH_APP_KEY'),

    'app_secret' => env('BKASH_APP_SECRET'),

    'username' => env('BKASH_USERNAME'),

    'password' => env('BKASH_PASSWORD'),

    'http_timeout' => (int) env('BKASH_HTTP_TIMEOUT', 30),

    /**
     * Must match URL registered in bKash merchant panel (domain whitelist).
     * If empty, uses route('bkash.callback') from APP_URL.
     */
    'callback_url' => env('BKASH_CALLBACK_URL'),

    /*
    | Snapshot of .env at bootstrap (safe when config is cached). Used when clearing DB overrides.
    */
    'env_defaults' => [
        'enabled' => (bool) env('BKASH_ENABLED', false),
        'gateway_type' => env('BKASH_GATEWAY_TYPE', 'tokenized_web'),
        'environment' => env('BKASH_ENVIRONMENT', 'sandbox'),
        'base_url' => rtrim(env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'), '/'),
        'activation_date' => env('BKASH_ACTIVATION_DATE'),
        'expiry_date' => env('BKASH_EXPIRY_DATE'),
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BKASH_CHANNELS', 'admin,public_pay,portal,reseller'))
        ))),
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
        'http_timeout' => (int) env('BKASH_HTTP_TIMEOUT', 30),
        'callback_url' => env('BKASH_CALLBACK_URL'),
    ],

];
