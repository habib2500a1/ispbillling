<?php

/**
 * ISP ticket categories — NOC + CRM + field support taxonomy.
 * Keys are stored in support_tickets.issue_type.
 */
return [

    'groups' => [
        'network' => [
            'label' => 'Network',
            'icon' => 'heroicon-o-signal',
            'items' => [
                'network_no_internet' => ['label' => 'No Internet', 'default_priority' => 'high'],
                'network_slow_speed' => ['label' => 'Slow Speed', 'default_priority' => 'medium'],
                'network_high_ping' => ['label' => 'High Ping', 'default_priority' => 'medium'],
                'network_packet_loss' => ['label' => 'Packet Loss', 'default_priority' => 'high'],
            ],
        ],
        'fiber' => [
            'label' => 'Fiber',
            'icon' => 'heroicon-o-bolt',
            'items' => [
                'fiber_cut' => ['label' => 'Fiber Cut', 'default_priority' => 'critical'],
                'fiber_low_signal' => ['label' => 'Low Signal', 'default_priority' => 'high'],
                'fiber_los' => ['label' => 'LOS', 'default_priority' => 'critical'],
            ],
        ],
        'onu' => [
            'label' => 'ONU',
            'icon' => 'heroicon-o-cpu-chip',
            'items' => [
                'onu_offline' => ['label' => 'ONU Offline', 'default_priority' => 'high'],
                'onu_faulty' => ['label' => 'ONU Faulty', 'default_priority' => 'high'],
                'onu_replace' => ['label' => 'ONU Replace', 'default_priority' => 'medium'],
            ],
        ],
        'billing' => [
            'label' => 'Billing',
            'icon' => 'heroicon-o-banknotes',
            'items' => [
                'billing_due' => ['label' => 'Due Issue', 'default_priority' => 'medium'],
                'billing_payment' => ['label' => 'Payment Not Updated', 'default_priority' => 'medium'],
                'billing_invoice' => ['label' => 'Invoice Problem', 'default_priority' => 'low'],
            ],
        ],
        'other' => [
            'label' => 'Others',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'items' => [
                'other_new_connection' => ['label' => 'New Connection', 'default_priority' => 'medium'],
                'other_relocation' => ['label' => 'Relocation', 'default_priority' => 'medium'],
                'other_package_change' => ['label' => 'Package Change', 'default_priority' => 'low'],
            ],
        ],
    ],

    /** Legacy issue_type keys → new keys (read-only migration hint). */
    'legacy_map' => [
        'billing' => 'billing_due',
        'connection' => 'network_no_internet',
        'speed' => 'network_slow_speed',
        'outage' => 'fiber_cut',
        'installation' => 'other_new_connection',
        'equipment' => 'onu_faulty',
        'pppoe' => 'network_no_internet',
        'fiber' => 'fiber_low_signal',
        'wifi' => 'network_slow_speed',
        'relocation' => 'other_relocation',
        'other' => 'other_new_connection',
    ],

    /** Corporate subscribers get +1 priority tier on offline/outage categories. */
    'corporate_boost_issue_types' => [
        'network_no_internet',
        'fiber_cut',
        'fiber_los',
        'onu_offline',
    ],

];
