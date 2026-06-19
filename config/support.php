<?php

return [

    'ticket_number_prefix' => env('SUPPORT_TICKET_PREFIX', 'ISP'),

    'default_sla_profile' => env('SUPPORT_DEFAULT_SLA_PROFILE', 'standard'),

    /**
     * Target resolution time from ticket creation (legacy fallback).
     */
    'sla_resolve_hours' => [
        'critical' => 1,
        'high' => 4,
        'medium' => 8,
        'low' => 24,
    ],

    /**
     * Corporate vs standard SLA — first-response (minutes) and resolve (hours) by priority.
     */
    'sla_profiles' => [
        'standard' => [
            'first_response_minutes' => [
                'critical' => 5,
                'high' => 15,
                'medium' => 30,
                'low' => 60,
            ],
            'resolve_hours' => [
                'critical' => 1,
                'high' => 4,
                'medium' => 8,
                'low' => 24,
            ],
        ],
        'corporate' => [
            'first_response_minutes' => [
                'low' => 120,
                'medium' => 60,
                'high' => 30,
                'critical' => 10,
            ],
            'resolve_hours' => [
                'low' => 48,
                'medium' => 24,
                'high' => 8,
                'critical' => 2,
            ],
        ],
    ],

    'mass_outage' => [
        'enabled' => (bool) env('SUPPORT_MASS_OUTAGE_ENABLED', true),
        'window_hours' => (int) env('SUPPORT_MASS_OUTAGE_WINDOW_HOURS', 4),
        'ticket_threshold' => (int) env('SUPPORT_MASS_OUTAGE_THRESHOLD', 5),
        'issue_types' => [
            'network_no_internet', 'network_slow_speed', 'network_packet_loss',
            'fiber_cut', 'fiber_los', 'fiber_low_signal', 'onu_offline',
            'connection', 'outage', 'speed', 'fiber',
        ],
    ],

    /** Escalation ladder when SLA breaches — level maps to role tier. */
    'escalation_ladder' => [
        ['level' => 0, 'label' => 'Support', 'role' => 'support'],
        ['level' => 1, 'label' => 'Senior Support', 'role' => 'senior_support'],
        ['level' => 2, 'label' => 'NOC Engineer', 'role' => 'noc_engineer'],
        ['level' => 3, 'label' => 'Manager', 'role' => 'manager'],
    ],

    /**
     * Shared secret for POST /api/webhooks/support-ticket-ingest (X-ISP-Webhook-Secret header).
     */
    'webhook_secret' => env('ISP_SUPPORT_WEBHOOK_SECRET'),
];
