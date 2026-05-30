<?php

return [
    'reseller_negative_balance' => [
        'enabled' => (bool) env('AUTOMATION_RESELLER_BALANCE_CHECK', true),
        'auto_deactivate' => (bool) env('AUTOMATION_RESELLER_AUTO_DEACTIVATE', false),
    ],

    'reseller_due_reminders' => [
        'enabled' => (bool) env('AUTOMATION_RESELLER_DUE_REMINDERS', true),
        'min_due_customers' => max(0, (int) env('AUTOMATION_RESELLER_DUE_MIN_CUSTOMERS', 1)),
        'min_due_amount' => max(0, (float) env('AUTOMATION_RESELLER_DUE_MIN_AMOUNT', 0)),
        'include_expiring' => (bool) env('AUTOMATION_RESELLER_DUE_INCLUDE_EXPIRING', true),
        'expiring_within_days' => max(1, (int) env('AUTOMATION_RESELLER_DUE_EXPIRING_DAYS', 3)),
        'dedupe_same_day' => (bool) env('AUTOMATION_RESELLER_DUE_DEDUPE', true),
    ],

    'postpaid_fund_credit' => [
        'enabled' => (bool) env('AUTOMATION_POSTPAID_FUND_CREDIT', true),
    ],

    'prepaid_wallet_settle' => [
        'enabled' => (bool) env('AUTOMATION_PREPAID_WALLET_SETTLE', true),
    ],

    'notify_on_failure' => (bool) env('AUTOMATION_NOTIFY_ON_FAILURE', true),

    'run_history_keep' => max(10, min(500, (int) env('AUTOMATION_RUN_HISTORY_KEEP', 100))),
];
