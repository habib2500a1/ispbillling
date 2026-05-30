<?php

return [
    'wallet_recharge' => [
        'enabled' => (bool) env('RESELLER_WALLET_RECHARGE_ENABLED', true),
        'manual_enabled' => (bool) env('RESELLER_WALLET_RECHARGE_MANUAL', true),
        'piprapay_enabled' => (bool) env('RESELLER_WALLET_RECHARGE_PIPRAPAY', true),
        'min_amount' => max(1.0, (float) env('RESELLER_WALLET_RECHARGE_MIN', 500)),
        'max_amount' => max(100.0, (float) env('RESELLER_WALLET_RECHARGE_MAX', 500000)),
    ],
];
