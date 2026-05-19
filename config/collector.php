<?php

return [

    'enabled' => (bool) env('COLLECTOR_SETTLEMENT_ENABLED', true),

    /** Roles shown when admin attributes a collection to field staff. */
    'collectable_roles' => ['cashier', 'branch-manager'],

    /** Cash/counter methods tracked as collector-held until settled to admin. */
    'cash_methods' => ['cash', 'counter'],

    'holding_account_code' => env('COLLECTOR_HOLDING_ACCOUNT_CODE', '1050'),

    /** Alert threshold for collector outstanding (BDT). */
    'due_alert_threshold' => (float) env('COLLECTOR_DUE_ALERT_THRESHOLD', 10000),

    'settlement_requires_approval' => (bool) env('COLLECTOR_SETTLEMENT_REQUIRES_APPROVAL', true),

    'expense_requires_approval' => (bool) env('COLLECTOR_EXPENSE_REQUIRES_APPROVAL', true),

    'cash_mismatch_threshold' => (float) env('COLLECTOR_CASH_MISMATCH_THRESHOLD', 50),

    'high_expense_threshold' => (float) env('COLLECTOR_HIGH_EXPENSE_THRESHOLD', 5000),

    /** @var array<string, string> */
    'expense_categories' => [
        'fuel' => 'Fuel',
        'transport' => 'Transport',
        'food' => 'Food',
        'maintenance' => 'Maintenance / repair',
        'device' => 'Device / ONU replacement',
        'salary_advance' => 'Salary advance',
        'fiber_repair' => 'Fiber repair',
        'misc' => 'Miscellaneous',
    ],

];
