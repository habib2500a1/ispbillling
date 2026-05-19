<?php

/**
 * Subscriber (customer) code generation & MikroTik import matching.
 *
 * code_format:
 * - prefixed_monthly  → CUST-2605-0001 (default)
 * - numeric           → 10001, 10002 …
 * - prefix_sequential → {code_prefix}-0001
 * - secret_as_code    → PPP secret name becomes customer_code (import / manual)
 */
return [

    'code_format' => env('SUBSCRIBER_CODE_FORMAT', 'prefixed_monthly'),

    'code_prefix' => env('SUBSCRIBER_CODE_PREFIX', 'CUST'),

    'numeric_start' => (int) env('SUBSCRIBER_NUMERIC_START', 10001),

    /**
     * When importing from MikroTik: set radius_username to secret name (recommended).
     */
    'import_set_radius_username' => (bool) env('SUBSCRIBER_IMPORT_SET_RADIUS_USERNAME', true),

    /**
     * Auto-import PPP secrets from all enabled routers (scheduler).
     */
    'auto_import_secrets_enabled' => (bool) env('SUBSCRIBER_AUTO_IMPORT_MIKROTIK_SECRETS', false),

    'auto_import_interval_minutes' => (int) env('SUBSCRIBER_AUTO_IMPORT_INTERVAL', 60),

];
