<?php

return [
    'enabled' => (bool) env('SSLCOMMERZ_ENABLED', false),
    'sandbox' => (bool) env('SSLCOMMERZ_SANDBOX', true),
    'store_id' => env('SSLCOMMERZ_STORE_ID'),
    'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
    'http_timeout' => (int) env('SSLCOMMERZ_HTTP_TIMEOUT', 30),
];
