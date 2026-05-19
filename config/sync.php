<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fast sync mode (recommended for 200+ subscribers)
    |--------------------------------------------------------------------------
    | Batches DB writes, caches subscriber lookups, skips redundant SNMP/API work.
    */
    'fast_mode' => filter_var(env('SYNC_FAST_MODE', true), FILTER_VALIDATE_BOOL),

    /** Run abuse detection every N minutes during bandwidth collect (not every tick). */
    'abuse_eval_interval_minutes' => max(5, (int) env('SYNC_ABUSE_EVAL_INTERVAL', 15)),

    /** Skip full /interface/print when /ppp/active already has byte counters. */
    'mikrotik_smart_interface_walk' => filter_var(env('SYNC_MIKROTIK_SMART_IFACE', true), FILTER_VALIDATE_BOOL),

    /** BDCOM full walk only in isp:sync-bdcom-epon-onus — not inside OLT poll. */
    'skip_bdcom_in_olt_poll' => filter_var(env('SYNC_SKIP_BDCOM_IN_POLL', true), FILTER_VALIDATE_BOOL),

    /** isp:poll-olt-intelligence — skip optical collect (use isp:collect-onu-signals). */
    'skip_optical_in_olt_poll' => filter_var(env('SYNC_SKIP_OPTICAL_IN_POLL', true), FILTER_VALIDATE_BOOL),

    /** Skip ONU auto-provision during OLT poll (run via ensure-customer-onus schedule). */
    'skip_provision_in_olt_poll' => filter_var(env('SYNC_SKIP_PROVISION_IN_POLL', true), FILTER_VALIDATE_BOOL),

    /** Skip GPON meta sync per OLT during poll when BDCOM/sync-onu handles it. */
    'skip_gpon_meta_in_olt_poll' => filter_var(env('SYNC_SKIP_GPON_IN_POLL', true), FILTER_VALIDATE_BOOL),

    /** WAN sampling during bandwidth collect (heavy). */
    'wan_on_bandwidth_collect' => filter_var(env('SYNC_WAN_ON_BANDWIDTH', false), FILTER_VALIDATE_BOOL),

    /** network-evaluate-access: only overdue + suspended subscribers. */
    'network_evaluate_only_candidates' => filter_var(env('SYNC_NETWORK_EVAL_FILTER', true), FILTER_VALIDATE_BOOL),

    /** Skip MikroTik API when desired access state already matches DB. */
    'skip_unchanged_network_sync' => filter_var(env('SYNC_SKIP_UNCHANGED_NETWORK', true), FILTER_VALIDATE_BOOL),

    /** MikroTik import: preload subscribers & skip unchanged rows. */
    'import_skip_unchanged' => filter_var(env('SYNC_IMPORT_SKIP_UNCHANGED', true), FILTER_VALIDATE_BOOL),

    /** Max rows per BandwidthSample::insert() batch. */
    'bandwidth_insert_batch' => max(50, min(1000, (int) env('SYNC_BANDWIDTH_BATCH', 250))),

];
