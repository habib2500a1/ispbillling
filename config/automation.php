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

    /** Global mutex for isp:run-automatic-processes (seconds). Must exceed longest OLT/MikroTik run. */
    'runner_lock_seconds' => max(60, (int) env('AUTOMATION_RUNNER_LOCK_SECONDS', 1800)),

    /** Max concurrent isp:run-automatic-processes OS processes before guard acts. */
    'max_runner_processes' => max(1, (int) env('AUTOMATION_MAX_RUNNER_PROCESSES', 1)),

    /** Kill excess workers only when older than this (seconds). */
    'min_runner_kill_seconds' => max(30, (int) env('AUTOMATION_MIN_RUNNER_KILL_SECONDS', 90)),

    /** Always kill workers older than this (seconds), even when count is within max. */
    'stale_runner_seconds' => max(120, (int) env('AUTOMATION_STALE_RUNNER_SECONDS', 600)),

    /** Truncate storage/logs/*.log when larger than this (bytes). */
    'log_prune_max_bytes' => max(1_000_000, (int) env('LOG_PRUNE_MAX_BYTES', 50_000_000)),

    /** Bytes to keep at the tail after prune. */
    'log_prune_keep_bytes' => max(100_000, (int) env('LOG_PRUNE_KEEP_BYTES', 2_000_000)),
];
