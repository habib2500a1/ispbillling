<?php

return [

    /** Hub dashboards (billing, NOC, support, etc.) — Livewire poll when tab visible. */
    'hub_poll_seconds' => max(30, (int) env('PERF_HUB_POLL_SECONDS', 60)),

    /** Main dashboard widgets (KPI grid, unified ops). */
    'widget_poll_seconds' => max(60, (int) env('PERF_WIDGET_POLL_SECONDS', 90)),

    /** Horizon queue monitor page. */
    'queue_monitor_poll_seconds' => max(30, (int) env('PERF_QUEUE_MONITOR_POLL_SECONDS', 45)),

    /** Cached hub snapshot TTL (finance, HR, ISP OS, comms). */
    'hub_cache_seconds' => max(60, (int) env('PERF_HUB_CACHE_SECONDS', 120)),

    /** Operations dashboard payload (main admin home widgets). */
    'ops_dashboard_cache_seconds' => max(60, (int) env('PERF_OPS_DASHBOARD_CACHE_SECONDS', 120)),

];
