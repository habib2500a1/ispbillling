<?php

namespace App\Filament\Widgets\Concerns;

use App\Services\Bandwidth\BandwidthSyncDispatcher;
use App\Support\TenantResolver;

trait PollsWanLiveSamples
{
    protected function getWanCollectInterval(): int
    {
        return max(3, (int) config('bandwidth.monitor_wan_collect_seconds', 3));
    }

    protected function getChartPollingInterval(): int
    {
        return max(1, (int) config('bandwidth.monitor_wan_chart_poll_seconds', 1));
    }

    public function pollWanLive(): void
    {
        try {
            app(BandwidthSyncDispatcher::class)->queueRefreshWanLiveSamples(
                TenantResolver::requiredTenantId(),
            );
        } catch (\Throwable) {
            // Keep last-known WAN samples on transient errors.
        }
    }
}
