<?php

return [
    'enabled' => (bool) env('NAGAD_ENABLED', false),

    /** api | personal — personal = Send Money + TrxID + SMS verify */
    'gateway_type' => env('NAGAD_GATEWAY_TYPE', 'api'),

    'personal_number' => env('NAGAD_PERSONAL_NUMBER'),

    'personal_name' => env('NAGAD_PERSONAL_NAME', env('ISP_COMPANY_NAME', 'ISP')),

    'instructions' => env('NAGAD_PERSONAL_INSTRUCTIONS'),

    'sandbox' => (bool) env('NAGAD_SANDBOX', true),
    'merchant_id' => env('NAGAD_MERCHANT_ID'),
    'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
    /** Nagad payment-gateway public key (encrypt sensitiveData). */
    'pg_public_key' => env('NAGAD_PG_PUBLIC_KEY', env('NAGAD_PUBLIC_KEY')),
    /** Merchant RSA private key (sign requests). */
    'merchant_private_key' => env('NAGAD_MERCHANT_PRIVATE_KEY', env('NAGAD_PRIVATE_KEY')),
    'http_timeout' => (int) env('NAGAD_HTTP_TIMEOUT', 30),
];
