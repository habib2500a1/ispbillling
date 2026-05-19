<?php

return [
    'enabled' => (bool) env('NAGAD_ENABLED', false),
    'sandbox' => (bool) env('NAGAD_SANDBOX', true),
    'merchant_id' => env('NAGAD_MERCHANT_ID'),
    'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
    /** Nagad payment-gateway public key (encrypt sensitiveData). */
    'pg_public_key' => env('NAGAD_PG_PUBLIC_KEY', env('NAGAD_PUBLIC_KEY')),
    /** Merchant RSA private key (sign requests). */
    'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY', env('NAGAD_PRIVATE_KEY')),
    'http_timeout' => (int) env('NAGAD_HTTP_TIMEOUT', 30),
];
