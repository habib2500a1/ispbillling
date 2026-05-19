<?php

return [

    'receipt_prefix' => env('PAYMENT_RECEIPT_PREFIX', 'RCP'),

    'receipt_year_infix' => env('PAYMENT_RECEIPT_YEAR_INFIX', true),

    /**
     * Shared secret for generic payment webhooks (per-gateway overrides below).
     */
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),

    'gateways' => [
        'bkash' => [
            'webhook_secret' => env('BKASH_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
            'enabled' => env('BKASH_ENABLED', false),
        ],
        'nagad' => [
            'webhook_secret' => env('NAGAD_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
            'enabled' => env('NAGAD_ENABLED', false),
        ],
        'rocket' => [
            'webhook_secret' => env('ROCKET_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
            'enabled' => env('ROCKET_ENABLED', false),
        ],
        'sslcommerz' => [
            'webhook_secret' => env('SSLCOMMERZ_WEBHOOK_SECRET', env('PAYMENT_WEBHOOK_SECRET')),
            'enabled' => env('SSLCOMMERZ_ENABLED', false),
        ],
        'piprapay' => [
            'webhook_secret' => env('PIPRAPAY_API_KEY', env('PAYMENT_WEBHOOK_SECRET')),
            'enabled' => env('PIPRAPAY_ENABLED', false),
        ],
        'stripe' => [
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'enabled' => env('STRIPE_ENABLED', false),
        ],
        'paypal' => [
            'webhook_secret' => env('PAYPAL_WEBHOOK_SECRET'),
            'enabled' => env('PAYPAL_ENABLED', false),
        ],
    ],

    /**
     * Overpayment on an invoice is credited to subscriber wallet automatically.
     */
    'overpayment_to_wallet' => env('PAYMENT_OVERPAYMENT_TO_WALLET', true),

];
