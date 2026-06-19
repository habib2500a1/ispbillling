<?php

return [

    'enabled' => (bool) env('NETWORK_CLEANUP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Suspended subscriber — safe MAC/network cleanup
    |--------------------------------------------------------------------------
    | NEVER runs on simple ONU offline. Requires customer suspended + offline grace.
    */
    'suspended' => [
        'enabled' => (bool) env('NETWORK_CLEANUP_SUSPENDED_ENABLED', true),
        /** Hours ONU must be offline before any network cleanup on suspended accounts. */
        'offline_grace_hours' => (int) env('NETWORK_CLEANUP_SUSPENDED_OFFLINE_HOURS', 24),
        'kick_ppp_sessions' => true,
        'ensure_ppp_disabled' => true,
        'remove_radius_user' => (bool) env('NETWORK_CLEANUP_SUSPENDED_RADIUS', false),
        /** Keep ONU inventory + MAC — do not delete OLT-side rows on offline. */
        'delete_onu_inventory' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminated / closed customer — full network teardown
    |--------------------------------------------------------------------------
    */
    'terminated' => [
        'enabled' => (bool) env('NETWORK_CLEANUP_TERMINATED_ENABLED', true),
        'offline_grace_days' => (int) env('NETWORK_CLEANUP_TERMINATED_OFFLINE_DAYS', 30),
        'kick_ppp_sessions' => true,
        'remove_ppp_secret' => true,
        'remove_radius_user' => true,
        'unlink_onu' => true,
        'delete_dhcp_lease' => (bool) env('NETWORK_CLEANUP_TERMINATED_DHCP', false),
    ],

    'log_actions' => true,

];
