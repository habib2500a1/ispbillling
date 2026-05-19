<?php

return [
    'reseller_negative_balance' => [
        'enabled' => (bool) env('AUTOMATION_RESELLER_BALANCE_CHECK', true),
        'auto_deactivate' => (bool) env('AUTOMATION_RESELLER_AUTO_DEACTIVATE', false),
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
