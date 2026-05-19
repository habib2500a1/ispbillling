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
];
