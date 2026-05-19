<?php

return [
    'two_factor_issuer' => env('STAFF_2FA_ISSUER', 'ISP Billing'),
    'recovery_code_count' => 8,
    'activity_log_retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365),
];
