<?php

return [

    'reminders_enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),

    'reminders_days_before' => (int) env('SMS_REMINDERS_DAYS', 3),

    /** @deprecated Use notifications.events.invoice_due — kept for backward compatibility */

    'env_defaults' => [
        'reminders_enabled' => (bool) env('SMS_REMINDERS_ENABLED', false),
        'reminders_days_before' => (int) env('SMS_REMINDERS_DAYS', 3),
    ],
];
