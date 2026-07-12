<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Support SLA (Code Pagol) — computed from created_at / replied_at
    | No extra DB columns required.
    |--------------------------------------------------------------------------
    */
    'sla_resolve_hours' => [
        'high' => (int) env('SUPPORT_SLA_HIGH_HOURS', 4),
        'medium' => (int) env('SUPPORT_SLA_MEDIUM_HOURS', 24),
        'low' => (int) env('SUPPORT_SLA_LOW_HOURS', 48),
    ],

    'sla_first_response_minutes' => [
        'high' => (int) env('SUPPORT_SLA_HIGH_FIRST_MIN', 30),
        'medium' => (int) env('SUPPORT_SLA_MEDIUM_FIRST_MIN', 120),
        'low' => (int) env('SUPPORT_SLA_LOW_FIRST_MIN', 240),
    ],
];
