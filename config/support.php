<?php

return [

    'ticket_number_prefix' => env('SUPPORT_TICKET_PREFIX', 'ISP'),

    'default_sla_profile' => env('SUPPORT_DEFAULT_SLA_PROFILE', 'standard'),

    /**
     * Target resolution time from ticket creation (legacy fallback).
     */
    'sla_resolve_hours' => [
        'low' => 72,
        'medium' => 48,
        'high' => 24,
        'critical' => 4,
    ],

    /**
     * Corporate vs standard SLA — first-response (minutes) and resolve (hours) by priority.
     */
    'sla_profiles' => [
        'standard' => [
            'first_response_minutes' => [
                'low' => 240,
                'medium' => 120,
                'high' => 60,
                'critical' => 15,
            ],
            'resolve_hours' => [
                'low' => 72,
                'medium' => 48,
                'high' => 24,
                'critical' => 4,
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
        'issue_types' => ['connection', 'outage', 'speed', 'fiber'],
    ],

    /**
     * Shared secret for POST /api/webhooks/support-ticket-ingest (X-ISP-Webhook-Secret header).
     */
    'webhook_secret' => env('ISP_SUPPORT_WEBHOOK_SECRET'),
];
