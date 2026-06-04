<?php

return [

    /*
    |--------------------------------------------------------------------------
    | bKash checkout (tokenized API, v1.2.0-beta)
    |--------------------------------------------------------------------------
    |
    | Personal (Send Money + TrxID) and Merchant API can both be enabled.
    |
    */

    'enabled' => (bool) env('BKASH_ENABLED', false),

    /** @deprecated Legacy; use personal_enabled + merchant_enabled */
    'gateway_type' => env('BKASH_GATEWAY_TYPE', 'tokenized_web'),

    /** Send Money + TrxID on /pay and portal */
    'personal_enabled' => (bool) env('BKASH_PERSONAL_ENABLED', false),

    /** Tokenized merchant checkout on /pay and portal */
    'merchant_enabled' => (bool) env('BKASH_MERCHANT_ENABLED', false),

    'personal_number' => env('BKASH_PERSONAL_NUMBER'),

    'personal_name' => env('BKASH_PERSONAL_NAME', env('ISP_COMPANY_NAME', 'ISP')),

    'instructions' => env('BKASH_PERSONAL_INSTRUCTIONS'),

    'environment' => env('BKASH_ENVIRONMENT', 'sandbox'),

    'base_url' => rtrim(env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'), '/'),

    'activation_date' => env('BKASH_ACTIVATION_DATE'),

    'expiry_date' => env('BKASH_EXPIRY_DATE'),

    /**
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

    'callback_url' => env('BKASH_CALLBACK_URL'),

    'env_defaults' => [
        'enabled' => (bool) env('BKASH_ENABLED', false),
        'gateway_type' => env('BKASH_GATEWAY_TYPE', 'tokenized_web'),
        'personal_enabled' => (bool) env('BKASH_PERSONAL_ENABLED', false),
        'merchant_enabled' => (bool) env('BKASH_MERCHANT_ENABLED', false),
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
