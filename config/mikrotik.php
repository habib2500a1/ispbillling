<?php

return [

    /** WAN routers with 400+ PPP sessions may need 90–120s on slow links. */
    'connection_timeout' => (int) env('MIKROTIK_CONNECTION_TIMEOUT', 30),

    'socket_timeout' => (int) env('MIKROTIK_SOCKET_TIMEOUT', 120),

    'poll_enabled' => (bool) env('MIKROTIK_POLL_STATUS_ENABLED', true),

    /** When true, scheduler runs isp:mikrotik-fetch-details (API-heavy; off by default). */
    'fetch_details_poll_enabled' => (bool) env('MIKROTIK_FETCH_DETAILS_POLL_ENABLED', false),

    /** Copy VLAN from PPP secret comment/profile into customer.meta.vlan (NOC / PON tables). */
    'auto_sync_vlan' => filter_var(env('MIKROTIK_AUTO_SYNC_VLAN', true), FILTER_VALIDATE_BOOL),

    /** Throttle VLAN re-sync during bandwidth collect (minutes per tenant). */
    'vlan_sync_throttle_minutes' => max(5, (int) env('MIKROTIK_VLAN_SYNC_THROTTLE', 30)),

    /** Copy RouterOS interface name onto OLT PON port label (from linked subscribers). */
    'auto_sync_pon_port_names' => filter_var(env('MIKROTIK_AUTO_SYNC_PON_PORT_NAMES', true), FILTER_VALIDATE_BOOL),

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
