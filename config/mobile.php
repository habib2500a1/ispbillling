<?php

return [
    /** GitHub repo for APK releases (no large binaries in git). */
    'github_repo' => env('MOBILE_GITHUB_REPO', 'habib2500a1/ispbillling'),

    /** Release tags on GitHub (assets: isp-radiant.apk, isp-mfs-verify.apk). */
    'radiant_github_tag' => env('MOBILE_RADIANT_GITHUB_TAG'),
    'mfs_github_tag' => env('MOBILE_MFS_GITHUB_TAG'),

    /** When true, download links point to GitHub Releases unless MOBILE_*_URL overrides. */
    'use_github_releases' => (bool) env('MOBILE_USE_GITHUB_RELEASES', true),

    /** Optional override URLs (landing page, admin panel). */
    'apk_download_url' => env('MOBILE_APK_URL'),
    'mfs_verify_apk_url' => env('MOBILE_MFS_VERIFY_APK_URL'),

    'fcm_enabled' => (bool) env('FCM_ENABLED', false),
    'fcm_server_key' => env('FCM_SERVER_KEY'),
    'customer_token_name' => 'customer-app',
    'technician_token_name' => 'technician-app',
    'customer_token_expiry_days' => (int) env('MOBILE_CUSTOMER_TOKEN_DAYS', 365),
    'staff_token_expiry_days' => (int) env('MOBILE_STAFF_TOKEN_DAYS', 180),
    'reseller_token_expiry_days' => (int) env('MOBILE_RESELLER_TOKEN_DAYS', 180),

    'ssl_pinning' => (bool) env('MOBILE_SSL_PINNING', false),

    'crash_reporting' => (bool) env('MOBILE_CRASH_REPORTING', true),

    /** When false, mobile app hides "Server settings" on login (single-domain production APK). */
    'allow_server_override' => (bool) env('MOBILE_ALLOW_SERVER_OVERRIDE', false),

    /** Short name shown on the phone launcher and login splash. */
    'app_display_name' => env('MOBILE_APP_DISPLAY_NAME', 'Radiant'),
];
