<?php

/**
 * Network access automation (MikroTik, RADIUS, OLT/ONU) — foundation only.
 *
 * Implement real drivers in App\Services\Network\* and set NETWORK_PROVISIONER_DRIVER.
 * Use `both` to run MikroTik + RADIUS stubs together; toggle each path via NETWORK_MIKROTIK_PUSH_ENABLED / NETWORK_RADIUS_PUSH_ENABLED or Filament → Integrations.
 * When provisioner_driver is null/log/radius, NETWORK_MIKROTIK_ALWAYS_PUSH_PPP_ON_CUSTOMER_SAVE (default true) still upserts /ppp/secret via RouterOS API if MikroTik push is on and the tenant has enabled panel servers.
 * OMCI/SNMP vendor hints: config/olt_vendors.php; use devices.meta for custom payloads.
 */
return [

    'provisioner_driver' => env('NETWORK_PROVISIONER_DRIVER', 'null'),

    /** When driver is `mikrotik`, `radius`, or `both`, gate suspend/sync to that backend (super-admin: Integrations). */
    'mikrotik_push_enabled' => (bool) env('NETWORK_MIKROTIK_PUSH_ENABLED', true),

    'radius_push_enabled' => (bool) env('NETWORK_RADIUS_PUSH_ENABLED', true),

    'auto_suspend_enabled' => (bool) env('NETWORK_AUTO_SUSPEND_ENABLED', false),

    'auto_suspend_grace_days' => (int) env('NETWORK_AUTO_SUSPEND_GRACE_DAYS', 0),

    'auto_suspend_min_balance' => (float) env('NETWORK_AUTO_SUSPEND_MIN_BALANCE', 1),

    /**
     * When true, suspend PPP when CustomerBalanceDue > 0 (not only past invoice due_date).
     * When false, only suspend after due_date + grace (legacy behaviour).
     */
    'suspend_on_any_balance_due' => (bool) env('NETWORK_SUSPEND_ON_ANY_BALANCE_DUE', true),

    'session_integrity_enabled' => (bool) env('NETWORK_SESSION_INTEGRITY_ENABLED', true),

    'integrity_auto_suspend_overdue' => (bool) env('NETWORK_INTEGRITY_AUTO_SUSPEND_OVERDUE', false),

    /**
     * When true, service_expires_at past calendar day → inactive + suspended network + MikroTik kick/disable.
     * Scheduler: isp:evaluate-service-expiry (see bootstrap/app.php).
     */
    'service_expiry_enforced' => (bool) env('NETWORK_SERVICE_EXPIRY_ENFORCED', true),

    /**
     * When true, customer save still runs RouterOS PPP upsert even if provisioner_driver is null/log/radius
     * (API path only; RADIUS remains separate).
     */
    'mikrotik_always_push_ppp_on_customer_save' => (bool) env('NETWORK_MIKROTIK_ALWAYS_PUSH_PPP_ON_CUSTOMER_SAVE', true),

    'olt_snmp_poll_enabled' => (bool) env('NETWORK_OLT_SNMP_POLL_ENABLED', true),

    /** CPU/RAM/temperature SNMP during isp:poll-olt-intelligence */
    'olt_health_poll_enabled' => (bool) env('NETWORK_OLT_HEALTH_POLL_ENABLED', true),

    /**
     * Legacy single-router env (unused). Use Filament → MikroTik servers (per-tenant DB credentials).
     */
    'mikrotik' => [
        'base_url' => rtrim((string) env('MIKROTIK_API_URL', ''), '/'),
        'user' => env('MIKROTIK_API_USER'),
        'password' => env('MIKROTIK_API_PASSWORD'),
        'timeout' => (int) env('MIKROTIK_API_TIMEOUT', 15),
        'use_ssl_verify' => (bool) env('MIKROTIK_SSL_VERIFY', true),
    ],

    'radius' => [
        'mode' => env('RADIUS_MODE', 'none'),
        'nas_identifier' => env('RADIUS_NAS_IDENTIFIER', 'isp-platform'),
    ],

    'env_defaults' => [
        'provisioner_driver' => env('NETWORK_PROVISIONER_DRIVER', 'null'),
        'mikrotik_push_enabled' => (bool) env('NETWORK_MIKROTIK_PUSH_ENABLED', true),
        'radius_push_enabled' => (bool) env('NETWORK_RADIUS_PUSH_ENABLED', true),
        'auto_suspend_enabled' => (bool) env('NETWORK_AUTO_SUSPEND_ENABLED', false),
        'auto_suspend_grace_days' => (int) env('NETWORK_AUTO_SUSPEND_GRACE_DAYS', 0),
        'auto_suspend_min_balance' => (float) env('NETWORK_AUTO_SUSPEND_MIN_BALANCE', 1),
        'suspend_on_any_balance_due' => (bool) env('NETWORK_SUSPEND_ON_ANY_BALANCE_DUE', true),
        'session_integrity_enabled' => (bool) env('NETWORK_SESSION_INTEGRITY_ENABLED', true),
        'integrity_auto_suspend_overdue' => (bool) env('NETWORK_INTEGRITY_AUTO_SUSPEND_OVERDUE', false),
        'service_expiry_enforced' => (bool) env('NETWORK_SERVICE_EXPIRY_ENFORCED', true),
        'mikrotik_always_push_ppp_on_customer_save' => (bool) env('NETWORK_MIKROTIK_ALWAYS_PUSH_PPP_ON_CUSTOMER_SAVE', true),
    ],
];
