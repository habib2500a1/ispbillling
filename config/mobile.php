<?php

return [
    'fcm_enabled' => (bool) env('FCM_ENABLED', false),
    'fcm_server_key' => env('FCM_SERVER_KEY'),
    'customer_token_name' => 'customer-app',
    'technician_token_name' => 'technician-app',
    'customer_token_expiry_days' => (int) env('MOBILE_CUSTOMER_TOKEN_DAYS', 90),
    'staff_token_expiry_days' => (int) env('MOBILE_STAFF_TOKEN_DAYS', 30),
];
