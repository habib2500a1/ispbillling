<?php

return [
  'merge_default_permissions' => (bool) env('RESELLER_MERGE_ENTERPRISE_PERMISSIONS', true),

    'automation' => [
        'low_balance_suspend' => (bool) env('RESELLER_AUTO_SUSPEND_LOW_BALANCE', true),
        'restore_on_recharge' => (bool) env('RESELLER_AUTO_RESTORE_RECHARGE', true),
    ],

    'api' => [
        'default_rate_limit' => (int) env('RESELLER_API_RATE_LIMIT', 120),
        'log_retention_days' => (int) env('RESELLER_API_LOG_RETENTION_DAYS', 90),
    ],

    'hierarchy' => [
        'max_depth' => (int) env('RESELLER_MAX_HIERARCHY_DEPTH', 50),
    ],

    'wallet' => [
        'ledger_enabled' => (bool) env('RESELLER_WALLET_LEDGER_ENABLED', true),
    ],

    'transfers' => [
        'require_admin_approval' => (bool) env('RESELLER_TRANSFER_REQUIRE_APPROVAL', true),
    ],
];
