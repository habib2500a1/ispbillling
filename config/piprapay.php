<?php

return [

    'enabled' => (bool) env('PIPRAPAY_ENABLED', false),

    /**
     * redirect = v3 hosted checkout (/checkout/redirect, /verify-payment)
     * legacy   = older create-charge API (sandbox.piprapay.com style)
     */
    'api_mode' => env('PIPRAPAY_API_MODE', 'redirect'),

    'sandbox' => (bool) env('PIPRAPAY_SANDBOX', true),

    'api_key' => env('PIPRAPAY_API_KEY'),

    'currency' => env('PIPRAPAY_CURRENCY', 'BDT'),

    /**
     * PipraPay panel “Base URL”, e.g. https://pay.flixbd.xyz/api
     * Checkout: {base_url}/checkout/redirect
     * Verify:   {base_url}/verify-payment
     */
    'base_url' => env('PIPRAPAY_BASE_URL', env('PIPRAPAY_SANDBOX', true)
        ? 'https://sandbox.piprapay.com/api'
        : 'https://piprapay.com/api'),

    /**
     * Public site URL for return_url / webhook_url (must be a domain PipraPay can whitelist).
     * Raw IPs like http://72.18.215.205 are rejected — use e.g. http://isp.flixbd.xyz or
     * http://72-18-215-205.sslip.io (same server, DNS points to your IP).
     */
    'public_url' => rtrim((string) env('PIPRAPAY_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),

    'http_timeout' => (int) env('PIPRAPAY_HTTP_TIMEOUT', 30),

];
