<?php

return [
    'wallet_recharge' => [
        'enabled' => (bool) env('RESELLER_WALLET_RECHARGE_ENABLED', true),
        'manual_enabled' => (bool) env('RESELLER_WALLET_RECHARGE_MANUAL', true),
        'piprapay_enabled' => (bool) env('RESELLER_WALLET_RECHARGE_PIPRAPAY', true),
        'min_amount' => max(1.0, (float) env('RESELLER_WALLET_RECHARGE_MIN', 500)),
        'max_amount' => max(100.0, (float) env('RESELLER_WALLET_RECHARGE_MAX', 500000)),
    ],

    /**
     * Debit reseller wallet for admin wholesale rate when a subscriber bill is created.
     */
    'wholesale_debit' => [
        'enabled' => (bool) env('RESELLER_WHOLESALE_DEBIT_ENABLED', true),
        'prorate_with_invoice' => (bool) env('RESELLER_WHOLESALE_PRORATE', true),
        /** When true, refuse new bills if wallet cannot cover wholesale (portal/API throws validation error). */
        'block_on_insufficient_balance' => (bool) env('RESELLER_WHOLESALE_BLOCK_INSUFFICIENT', false),
    ],

    /**
     * When admin sets reseller Inactive, suspend all subscribers under them; restore on Active.
     */
    'subscriber_sync' => [
        'enabled' => (bool) env('RESELLER_SUBSCRIBER_SYNC_ENABLED', true),
    ],
];
