<?php

return [

    /**
     * FreeRADIUS accounting DB (radacct). Set RADIUS_ACCOUNTING_ENABLED=true and DB credentials.
     */
    'accounting_enabled' => (bool) env('RADIUS_ACCOUNTING_ENABLED', false),

    'radacct_table' => env('RADIUS_RADACCT_TABLE', 'radacct'),

    /** Interim interval on NAS (seconds) — used for rate estimation when API is down. */
    'interim_interval' => (int) env('RADIUS_INTERIM_INTERVAL', 300),

    /**
     * When true, merge RADIUS radacct with MikroTik API active sessions (recommended for ISP billing).
     */
    'merge_with_api' => (bool) env('RADIUS_MERGE_WITH_API', true),

    /** Prefer API live counters when both sources see the same user. */
    'prefer_api_for_live_rates' => (bool) env('RADIUS_PREFER_API_RATES', true),

];
