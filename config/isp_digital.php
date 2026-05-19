<?php

return [
    'base_url' => rtrim((string) env('ISP_DIGITAL_URL', 'https://pay.anetbd.com'), '/'),
    'username' => (string) env('ISP_DIGITAL_USERNAME', 'admin'),
    'password' => (string) env('ISP_DIGITAL_PASSWORD', ''),
];
