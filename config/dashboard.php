<?php

return [

    /** Cache dashboard SSE snapshot payload (seconds). */
    'snapshot_cache_seconds' => (int) env('DASHBOARD_SNAPSHOT_CACHE_SECONDS', 45),

    /** Server-sent events interval on dashboard (seconds). */
    'stream_interval_seconds' => (int) env('DASHBOARD_STREAM_INTERVAL_SECONDS', 30),

    /** Max SSE payloads per HTTP request (avoids holding PHP-FPM workers for hours). */
    'stream_max_iterations' => (int) env('DASHBOARD_STREAM_MAX_ITERATIONS', 6),

    /** Cache online-users chart series (minutes). */
    'online_trend_cache_minutes' => (int) env('DASHBOARD_ONLINE_TREND_CACHE_MINUTES', 5),

];
