<?php

return [

    /** Cache dashboard SSE snapshot payload (seconds). */
    'snapshot_cache_seconds' => (int) env('DASHBOARD_SNAPSHOT_CACHE_SECONDS', 90),

    /** Server-sent events interval on dashboard (seconds). */
    'stream_interval_seconds' => (int) env('DASHBOARD_STREAM_INTERVAL_SECONDS', 60),

    /** Max SSE payloads per HTTP request (avoids holding PHP-FPM workers for hours). */
    'stream_max_iterations' => (int) env('DASHBOARD_STREAM_MAX_ITERATIONS', 6),

    /** Cache online-users chart series (minutes). */
    'online_trend_cache_minutes' => (int) env('DASHBOARD_ONLINE_TREND_CACHE_MINUTES', 5),

    /** Cache revenue trend chart (minutes). */
    'revenue_trend_cache_minutes' => (int) env('DASHBOARD_REVENUE_TREND_CACHE_MINUTES', 5),

    /** Cache NOC dashboard snapshot (seconds). */
    'noc_snapshot_cache_seconds' => (int) env('DASHBOARD_NOC_SNAPSHOT_CACHE_SECONDS', 60),

    /** NOC wall bundled payload cache (seconds). */
    'noc_wall_cache_seconds' => (int) env('DASHBOARD_NOC_WALL_CACHE_SECONDS', 60),

    /** NOC wall Livewire poll interval (seconds). */
    'noc_wall_poll_seconds' => (int) env('DASHBOARD_NOC_WALL_POLL_SECONDS', 90),

    /** NOC wall SSE stream interval (seconds). */
    'noc_stream_interval_seconds' => (int) env('DASHBOARD_NOC_STREAM_INTERVAL_SECONDS', 30),

    /** Max SSE payloads per NOC wall HTTP request. */
    'noc_stream_max_iterations' => (int) env('DASHBOARD_NOC_STREAM_MAX_ITERATIONS', 6),

];
