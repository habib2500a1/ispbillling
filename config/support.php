<?php

return [

    /**
     * Target resolution time from ticket creation (used for SLA due timestamp).
     */
    'sla_resolve_hours' => [
        'low' => 72,
        'medium' => 48,
        'high' => 24,
        'critical' => 4,
    ],

    /**
     * Shared secret for POST /api/webhooks/support-ticket-ingest (X-ISP-Webhook-Secret header).
     */
    'webhook_secret' => env('ISP_SUPPORT_WEBHOOK_SECRET'),
];
