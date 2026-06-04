<?php

namespace App\Services\Bandwidth;

use App\Jobs\CollectBandwidthForTenantJob;
use App\Jobs\RefreshWanLiveSamplesJob;

final class BandwidthSyncDispatcher
{
    public function queueCollectForTenant(int $tenantId): bool
    {
        if (! config('bandwidth.collection_enabled', true)) {
            return false;
        }

        if (BandwidthSyncStatus::isRunning($tenantId)) {
            return false;
        }

        if (! $this->shouldQueueFromWeb()) {
            app(BandwidthCollectionService::class)->collectForTenant($tenantId);

            return true;
        }

        BandwidthSyncStatus::markRunning($tenantId, 'collect');
        CollectBandwidthForTenantJob::dispatch($tenantId);

        return true;
    }

    public function queueRefreshWanLiveSamples(int $tenantId, bool $force = false): bool
    {
        if (! config('bandwidth.collection_enabled', true)) {
            return false;
        }

        if (! $this->shouldQueueFromWeb()) {
            app(BandwidthCollectionService::class)->refreshWanLiveSamples($tenantId, $force);

            return true;
        }

        RefreshWanLiveSamplesJob::dispatch($tenantId, $force);

        return true;
    }

    private function shouldQueueFromWeb(): bool
    {
        return filter_var(config('bandwidth.queue_sync_from_web', true), FILTER_VALIDATE_BOOL);
    }
}
