<?php

return [

    'mikrotik_offline_enabled' => (bool) env('ALERTS_MIKROTIK_OFFLINE_ENABLED', true),

    'sms_failure_rate_threshold' => (float) env('ALERTS_SMS_FAILURE_RATE', 0.25),

    'sms_failure_check_hours' => (int) env('ALERTS_SMS_FAILURE_HOURS', 24),

    'pending_payment_enabled' => (bool) env('ALERTS_PENDING_PAYMENT_ENABLED', true),

    'session_integrity_enabled' => (bool) env('ALERTS_SESSION_INTEGRITY_ENABLED', true),

    'queue_health_enabled' => (bool) env('ALERTS_QUEUE_HEALTH_ENABLED', true),

    'queue_failed_jobs_threshold' => (int) env('ALERTS_QUEUE_FAILED_THRESHOLD', 5),

    'queue_failed_check_minutes' => (int) env('ALERTS_QUEUE_FAILED_MINUTES', 15),

    'ops_email' => env('ALERTS_OPS_EMAIL'),

    'ops_sms_phone' => env('ALERTS_OPS_SMS_PHONE'),

];
