<?php

return [

    /** Master switch: /login and /portal/* (customer self-service). Bill pay (/pay) stays available. */
    'enabled' => (bool) env('PORTAL_ENABLED', true),

    'session' => [
        'remember_default' => (bool) env('PORTAL_REMEMBER_DEFAULT', env('AUTH_REMEMBER_DEFAULT', true)),
    ],

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

    'poll_seconds' => max(1, (int) env('PORTAL_POLL_SECONDS', 1)),

    'whatsapp_url' => env('PORTAL_WHATSAPP_URL'),

    'support_phone' => env('PORTAL_SUPPORT_PHONE'),

    'speed_test' => [
        /** Full-page embed for /portal/speed-test (Singapore broadband test). */
        'embed_url' => (string) env('PORTAL_SPEED_TEST_EMBED_URL', 'https://www.speedtest.sg/'),
        /*
        | Native UI (our START button) measures against an external CORS-enabled
        | backend so results reflect the internet path, not our own server.
        | speedtest.sg endpoints send Access-Control-Allow-Origin: *.
        */
        'external' => [
            'enabled' => (bool) env('PORTAL_SPEED_TEST_EXTERNAL', true),
            'ping_url' => (string) env('PORTAL_SPEED_TEST_PING_URL', 'https://www.speedtest.sg/speedtest/ping.php'),
            'download_url' => (string) env('PORTAL_SPEED_TEST_DOWNLOAD_URL', 'https://www.speedtest.sg/speedtest/download.php'),
            'upload_url' => (string) env('PORTAL_SPEED_TEST_UPLOAD_URL', 'https://www.speedtest.sg/speedtest/upload.php'),
        ],
        'download_bytes' => (int) env('PORTAL_SPEED_TEST_BYTES', 1_048_576),
        /** ~1 second quick check on usage page (512 KB default). */
        'quick_download_bytes' => (int) env('PORTAL_SPEED_TEST_QUICK_BYTES', 524_288),
        /** Multipart upload file size (Laravel max rule is KB). */
        'upload_kilobytes' => (int) env('PORTAL_SPEED_TEST_UPLOAD_KB', 768),
        'upload_bytes' => (int) env('PORTAL_SPEED_TEST_UPLOAD_BYTES', 262_144),
    ],

    /** Default customer portal password for new subscribers (user can change later). */
    'default_password' => (string) env('PORTAL_DEFAULT_PASSWORD', '123456'),

    /**
     * Mini portal for customer home router admin pages (iframe / bookmark).
     * URL: /router — auto-identifies by PPP public IP when online.
     */
    'router_home' => [
        'enabled' => (bool) env('PORTAL_ROUTER_HOME_ENABLED', true),
    ],

    'env_defaults' => [
        'enabled' => (bool) env('PORTAL_ENABLED', true),
        'otp_enabled' => (bool) env('PORTAL_OTP_ENABLED', false),
        'otp_log_delivery_only' => (bool) env('PORTAL_OTP_LOG_ONLY', false),
        'otp_ttl_seconds' => max(60, min(3600, (int) env('PORTAL_OTP_TTL_SECONDS', 600))),
        'otp_digits' => max(4, min(8, (int) env('PORTAL_OTP_DIGITS', 6))),
    ],
];
