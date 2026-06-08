<?php

namespace App\Support;

final class PerformanceSettings
{
    public static function hubPollSeconds(): int
    {
        return max(30, (int) config('performance.hub_poll_seconds', 60));
    }

    public static function widgetPollSeconds(): int
    {
        return max(60, (int) config('performance.widget_poll_seconds', 90));
    }

    public static function queueMonitorPollSeconds(): int
    {
        return max(30, (int) config('performance.queue_monitor_poll_seconds', 45));
    }

    public static function hubCacheSeconds(): int
    {
        return max(60, (int) config('performance.hub_cache_seconds', 120));
    }

    public static function opsDashboardCacheSeconds(): int
    {
        return max(60, (int) config('performance.ops_dashboard_cache_seconds', 120));
    }
}
