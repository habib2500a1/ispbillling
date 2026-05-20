<?php

return [

    /**
     * FreeRADIUS accounting DB (radacct). Enable in admin → Network → API & RADIUS setup.
     */
    'accounting_enabled' => (bool) env('RADIUS_ACCOUNTING_ENABLED', false),

    /** DB connection (panel overrides via AppSetting radius.db.*). */
    'db' => [
        'driver' => env('RADIUS_DB_DRIVER', 'mysql'),
        'host' => env('RADIUS_DB_HOST', '127.0.0.1'),
        'port' => (int) env('RADIUS_DB_PORT', 3306),
        'database' => env('RADIUS_DB_DATABASE', 'radius'),
        'username' => env('RADIUS_DB_USERNAME', 'radius'),
        'password' => env('RADIUS_DB_PASSWORD', ''),
    ],

    'radacct_table' => env('RADIUS_RADACCT_TABLE', 'radacct'),

    /** Interim interval on NAS (seconds) — used for rate estimation when API is down. */
    'interim_interval' => (int) env('RADIUS_INTERIM_INTERVAL', 300),

    /**
     * When true, merge RADIUS radacct with MikroTik API active sessions (recommended for ISP billing).
     */
    'merge_with_api' => (bool) env('RADIUS_MERGE_WITH_API', true),

    /** Prefer API live counters when both sources see the same user. */
    'prefer_api_for_live_rates' => (bool) env('RADIUS_PREFER_API_RATES', true),

    /** Max radacct rows per collect (tenant-filtered). */
    'max_active_sessions' => (int) env('RADIUS_MAX_ACTIVE_SESSIONS', 10000),

    /** Dangerous: global radacct fetch without tenant login filter. */
    'allow_global_fetch' => filter_var(env('RADIUS_ALLOW_GLOBAL_FETCH', false), FILTER_VALIDATE_BOOL),

];
