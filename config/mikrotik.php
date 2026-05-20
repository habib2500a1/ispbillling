<?php

return [

    'connection_timeout' => (int) env('MIKROTIK_CONNECTION_TIMEOUT', 20),

    'socket_timeout' => (int) env('MIKROTIK_SOCKET_TIMEOUT', 45),

    'poll_enabled' => (bool) env('MIKROTIK_POLL_STATUS_ENABLED', true),

    /** When true, scheduler runs isp:mikrotik-fetch-details (API-heavy; off by default). */
    'fetch_details_poll_enabled' => (bool) env('MIKROTIK_FETCH_DETAILS_POLL_ENABLED', false),

    /**
     * When true, suspend/unsuspend PPP only on the customer's assigned MikroTik (mikrotik_server_id).
     * Recommended when you have 2+ routers with different subscriber pools.
     */
    'provision_assigned_server_only' => (bool) env('MIKROTIK_PROVISION_ASSIGNED_SERVER_ONLY', true),

    /** RouterOS API retries per operation. */
    'api_max_attempts' => (int) env('MIKROTIK_API_MAX_ATTEMPTS', 3),

    'retry_delay_ms' => (int) env('MIKROTIK_RETRY_DELAY_MS', 400),

    'circuit_breaker_enabled' => filter_var(env('MIKROTIK_CIRCUIT_BREAKER_ENABLED', true), FILTER_VALIDATE_BOOL),

    'circuit_failure_threshold' => (int) env('MIKROTIK_CIRCUIT_FAILURE_THRESHOLD', 3),

    'circuit_open_seconds' => (int) env('MIKROTIK_CIRCUIT_OPEN_SECONDS', 120),
];
