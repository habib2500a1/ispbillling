<?php

return [
    'enabled' => (bool) env('NETFLOW_ENABLED', true),
    'listen_port' => (int) env('NETFLOW_LISTEN_PORT', 2055),
    'webhook_secret' => env('NETFLOW_WEBHOOK_SECRET'),
    'inbox_path' => storage_path('app/netflow/inbox'),
    'retention_days' => (int) env('NETFLOW_RETENTION_DAYS', 7),
    'aggregate_top_n' => 25,

    /** Evaluate top talkers after inbox import (GB per subscriber in window). */
    'abuse_eval_enabled' => filter_var(env('NETFLOW_ABUSE_EVAL_ENABLED', true), FILTER_VALIDATE_BOOL),
    'abuse_window_hours' => (int) env('NETFLOW_ABUSE_WINDOW_HOURS', 24),
    'abuse_threshold_gb' => (float) env('NETFLOW_ABUSE_THRESHOLD_GB', 200),
    /** alert | suspend */
    'abuse_action' => env('NETFLOW_ABUSE_ACTION', 'alert'),
];
