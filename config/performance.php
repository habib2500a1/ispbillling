<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Performance & polling — managed from Settings → Performance
    |--------------------------------------------------------------------------
    | Defaults seed into app_settings on first deploy. Override in dashboard;
    | .env optional for advanced installs only.
    */

    'env_defaults' => [
        // Subscriber page speed (biggest UX win)
        'optical.auto_sync_on_customer_view' => false,
        'optical.auto_sync_on_customer_save' => true,
        'optical.legacy_portal_auto_sync' => true,
        'optical.auto_sync_olt_on_mac_lookup' => true,
        'optical.customer_sync_connection' => 'redis',

        // Background polling load
        'optical.poll_interval_minutes' => 10,
        'bandwidth.poll_interval_minutes' => 5,
        'mikrotik.poll_enabled' => true,
        'mikrotik.fetch_details_poll_enabled' => false,
        'network.olt_snmp_poll_enabled' => true,

        // Platform speed
        'sync.fast_mode' => true,
        'isp.assets.bundle_css' => true,
        'isp.app_settings_sync_cache_seconds' => 120,

        // Scheduler safety (502 prevention)
        'automation.max_runner_processes' => 1,
        'automation.runner_lock_seconds' => 1800,
    ],

];
