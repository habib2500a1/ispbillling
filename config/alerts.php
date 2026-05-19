<?php

return [

    'mikrotik_offline_enabled' => (bool) env('ALERTS_MIKROTIK_OFFLINE_ENABLED', true),

    'sms_failure_rate_threshold' => (float) env('ALERTS_SMS_FAILURE_RATE', 0.25),

    'sms_failure_check_hours' => (int) env('ALERTS_SMS_FAILURE_HOURS', 24),

    'pending_payment_enabled' => (bool) env('ALERTS_PENDING_PAYMENT_ENABLED', true),

    'session_integrity_enabled' => (bool) env('ALERTS_SESSION_INTEGRITY_ENABLED', true),

];
