<?php

return [

    'collection_enabled' => env('BANDWIDTH_COLLECTION_ENABLED', true),

    /** How often scheduler runs isp:collect-bandwidth (minutes). */
    'poll_interval_minutes' => (int) env('BANDWIDTH_POLL_INTERVAL', 5),

    /** Auto-sync MikroTik while Online clients page is open (seconds). 0 = off. */
    'live_page_poll_seconds' => (int) env('BANDWIDTH_LIVE_PAGE_POLL_SECONDS', 60),

    /** Dashboard live bandwidth chart refresh (seconds). */
    'live_chart_poll_seconds' => (int) env('BANDWIDTH_LIVE_CHART_POLL_SECONDS', 2),

    /** Rolling per-second points on dashboard chart. */
    'live_chart_points' => (int) env('BANDWIDTH_LIVE_CHART_POINTS', 120),

    /**
     * MikroTik WAN/uplink interface names (comma-separated).
     * Matched case-insensitively; also matches names containing "wan".
     */
    'wan_interface_names' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BANDWIDTH_WAN_INTERFACES', 'ether1,ether2,wan,sfp1'))
    ))),

    /** Match any running MikroTik ethernet port when name patterns miss (slave ports excluded). */
    'wan_match_running_ether' => filter_var(env('BANDWIDTH_WAN_MATCH_RUNNING_ETHER', false), FILTER_VALIDATE_BOOL),

    /** Minimum seconds between WAN byte-delta samples (live graphs). */
    'wan_min_rate_interval_seconds' => (float) env('BANDWIDTH_WAN_MIN_RATE_INTERVAL_SECONDS', 1),

    /** Keep WAN interface samples (hours). */
    'wan_sample_retention_hours' => (int) env('BANDWIDTH_WAN_SAMPLE_RETENTION_HOURS', 72),

    /** Bandwidth monitor page: page-level live refresh (seconds). */
    'monitor_page_poll_seconds' => (int) env('BANDWIDTH_MONITOR_PAGE_POLL_SECONDS', 5),

    /** WAN widgets on bandwidth monitor (seconds). 0 = off (prevents Livewire timeout loops). */
    'monitor_wan_poll_seconds' => (int) env('BANDWIDTH_MONITOR_WAN_POLL_SECONDS', 0),

    /** WAN chart rolling window. */
    'monitor_wan_chart_points' => (int) env('BANDWIDTH_MONITOR_WAN_CHART_POINTS', 180),
    'monitor_wan_chart_minutes' => (int) env('BANDWIDTH_MONITOR_WAN_CHART_MINUTES', 15),

    /** Collect full PPP sync on each bandwidth-monitor poll (heavy — use Sync now instead). */
    'monitor_collect_on_poll' => filter_var(env('BANDWIDTH_MONITOR_COLLECT_ON_POLL', false), FILTER_VALIDATE_BOOL),

    /** Per-subscriber traffic chart refresh (seconds). 0 = off. */
    'subscriber_chart_poll_seconds' => (int) env('BANDWIDTH_SUBSCRIBER_CHART_POLL_SECONDS', 5),

    /** Rolling chart points kept in memory (1 point per poll). */
    'subscriber_chart_points' => (int) env('BANDWIDTH_SUBSCRIBER_CHART_POINTS', 120),

    /** Poll MikroTik for one login while live graph is open. */
    'subscriber_live_mikrotik_enabled' => filter_var(
        env('BANDWIDTH_SUBSCRIBER_LIVE_MIKROTIK', true),
        FILTER_VALIDATE_BOOL,
    ),

    /** Minimum seconds between byte-delta rate samples (subscriber live). */
    'subscriber_live_min_interval_seconds' => (float) env('BANDWIDTH_SUBSCRIBER_LIVE_MIN_INTERVAL', 5),

    /** Write live rates to PPP session meta every N chart ticks. */
    'subscriber_session_persist_every_ticks' => (int) env('BANDWIDTH_SUBSCRIBER_SESSION_PERSIST_TICKS', 5),

    /** Run full bandwidth collect while subscriber traffic page is open. */
    'subscriber_view_collect_on_poll' => filter_var(
        env('BANDWIDTH_SUBSCRIBER_VIEW_COLLECT_ON_POLL', false),
        FILTER_VALIDATE_BOOL,
    ),

    /** Minimum seconds between samples before delta-based live rate is shown. */
    'min_rate_interval_seconds' => (int) env('BANDWIDTH_MIN_RATE_INTERVAL_SECONDS', 45),

    /** Ignore delta rates above this (bps) — counter reset / too-short interval. */
    'max_sane_rate_bps' => (int) env('BANDWIDTH_MAX_SANE_RATE_BPS', 10_000_000_000),

    /** Keep raw samples for charts (hours). */
    'sample_retention_hours' => (int) env('BANDWIDTH_SAMPLE_RETENTION_HOURS', 72),

    /** Daily usage over package quota triggers alert (multiplier). */
    'daily_quota_multiplier' => (float) env('BANDWIDTH_DAILY_QUOTA_MULTIPLIER', 1.2),

    /** Sustained rate above package Mbps * this factor triggers alert. */
    'speed_burst_multiplier' => (float) env('BANDWIDTH_SPEED_BURST_MULTIPLIER', 1.15),

    /** Samples above burst threshold needed before alert. */
    'speed_burst_sample_count' => (int) env('BANDWIDTH_SPEED_BURST_SAMPLES', 3),

    /** Max concurrent PPP sessions per subscriber before alert. */
    'max_concurrent_sessions' => (int) env('BANDWIDTH_MAX_CONCURRENT_SESSIONS', 1),

    /** Auto-suspend on abuse alerts (e.g. NetFlow threshold, daily quota when enabled). */
    'abuse_auto_enforce_enabled' => filter_var(env('BANDWIDTH_ABUSE_AUTO_ENFORCE', false), FILTER_VALIDATE_BOOL),

    /** Suspend when daily package quota is exceeded. */
    'abuse_suspend_on_daily_quota' => filter_var(env('BANDWIDTH_ABUSE_SUSPEND_DAILY', false), FILTER_VALIDATE_BOOL),

];
