<?php

return [
    'otp' => [
        'enabled' => (bool) env('BILL_PAY_OTP_ENABLED', false),
        'ttl_seconds' => max(60, min(1800, (int) env('BILL_PAY_OTP_TTL_SECONDS', 600))),
        'digits' => max(4, min(8, (int) env('BILL_PAY_OTP_DIGITS', 6))),
        'log_delivery_only' => (bool) env('BILL_PAY_OTP_LOG_ONLY', false),
    ],

    'env_defaults' => [
        'otp_enabled' => (bool) env('BILL_PAY_OTP_ENABLED', false),
        'otp_log_delivery_only' => (bool) env('BILL_PAY_OTP_LOG_ONLY', false),
        'otp_ttl_seconds' => max(60, min(1800, (int) env('BILL_PAY_OTP_TTL_SECONDS', 600))),
        'otp_digits' => max(4, min(8, (int) env('BILL_PAY_OTP_DIGITS', 6))),
    ],

    /** Client self-pay must clear full invoice balance (no manual partial amount). */
    'allow_partial' => (bool) env('BILL_PAY_ALLOW_PARTIAL', false),

    'min_amount' => (float) env('BILL_PAY_MIN_AMOUNT', 10),

    'wallet_topup_enabled' => (bool) env('BILL_PAY_WALLET_TOPUP', true),

    'wallet_topup_min' => (float) env('BILL_PAY_WALLET_TOPUP_MIN', 100),

    'link_ttl_days' => (int) env('BILL_PAY_LINK_TTL_DAYS', 7),

    // Synced from bkash/sslcommerz/nagad.enabled after AppSetting::syncToRuntimeConfig().
    'gateways' => [
        'bkash' => (bool) env('BKASH_ENABLED', false),
        'sslcommerz' => (bool) env('SSLCOMMERZ_ENABLED', false),
        'nagad' => (bool) env('NAGAD_ENABLED', false),
        'rocket' => (bool) env('ROCKET_ENABLED', false),
    ],
];
