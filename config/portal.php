<?php

return [

    /** Master switch: /login and /portal/* (customer self-service). Bill pay (/pay) stays available. */
    'enabled' => (bool) env('PORTAL_ENABLED', true),

    /*
    | Optional second step after portal password: one-time code (email and/or log).
    | Super-admin / isp-admin / isp-manager: Filament → System → Customer portal.
    | Keys in app_settings: portal.otp.enabled, portal.otp.log_delivery_only, portal.otp.ttl_seconds, portal.otp.digits
    */
    'otp' => [
        'enabled' => (bool) env('PORTAL_OTP_ENABLED', false),
        'ttl_seconds' => max(60, min(3600, (int) env('PORTAL_OTP_TTL_SECONDS', 600))),
        'digits' => max(4, min(8, (int) env('PORTAL_OTP_DIGITS', 6))),
        'log_delivery_only' => (bool) env('PORTAL_OTP_LOG_ONLY', false),
    ],

    'signup' => [
        'enabled' => (bool) env('PORTAL_SIGNUP_ENABLED', true),
    ],

    'poll_seconds' => max(3, (int) env('PORTAL_POLL_SECONDS', 5)),

    'whatsapp_url' => env('PORTAL_WHATSAPP_URL'),

    'support_phone' => env('PORTAL_SUPPORT_PHONE'),

    'speed_test' => [
        'download_bytes' => (int) env('PORTAL_SPEED_TEST_BYTES', 2_097_152),
    ],

    /** Default customer portal password for new subscribers (user can change later). */
    'default_password' => (string) env('PORTAL_DEFAULT_PASSWORD', '123456'),

    'env_defaults' => [
        'enabled' => (bool) env('PORTAL_ENABLED', true),
        'otp_enabled' => (bool) env('PORTAL_OTP_ENABLED', false),
        'otp_log_delivery_only' => (bool) env('PORTAL_OTP_LOG_ONLY', false),
        'otp_ttl_seconds' => max(60, min(3600, (int) env('PORTAL_OTP_TTL_SECONDS', 600))),
        'otp_digits' => max(4, min(8, (int) env('PORTAL_OTP_DIGITS', 6))),
    ],
];
