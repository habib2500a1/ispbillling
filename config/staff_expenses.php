<?php

return [
    'requires_approval' => true,

    'sources' => [
        'vendor' => 'Vendor / supplier',
        'office' => 'Office cost',
        'other' => 'Other cost',
    ],

    'payment_methods' => [
        'cash' => 'Cash',
        'bank' => 'Bank transfer',
        'bkash' => 'bKash / MFS',
        'cheque' => 'Cheque',
        'other' => 'Other',
    ],

    /** @var array<string, array<string, string>> source => code => name */
    'categories' => [
        'vendor' => [
            'vendor_goods' => 'Goods / equipment purchase',
            'vendor_service' => 'Vendor service bill',
            'vendor_maintenance' => 'Maintenance contract',
        ],
        'office' => [
            'office_rent' => 'Office rent',
            'utilities' => 'Electric / gas / water',
            'internet' => 'Internet / connectivity',
            'stationery' => 'Stationery & supplies',
            'staff_food' => 'Staff food / refreshment',
        ],
        'other' => [
            'transport' => 'Transport / fuel',
            'marketing' => 'Marketing / promotion',
            'misc' => 'Miscellaneous',
        ],
    ],
];
