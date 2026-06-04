<?php

namespace App\Jobs;

use App\Services\Bandwidth\BandwidthCollectionService;
use App\Services\Bandwidth\BandwidthSyncStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectBandwidthForTenantJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public int $uniqueFor = 120;

    public function __construct(
        public int $tenantId,
    ) {
        $this->onQueue((string) config('bandwidth.queue_name', 'bandwidth'));
    }

    public function uniqueId(): string
    {
        return 'collect-bandwidth-tenant-'.$this->tenantId;
    }

    public function handle(BandwidthCollectionService $collector): void
    {
        if (! config('bandwidth.collection_enabled', true)) {
            BandwidthSyncStatus::clearRunning($this->tenantId);

            return;
        }

        try {
            $collector->collectForTenant($this->tenantId);
        } catch (\Throwable $e) {
            Log::warning('bandwidth.collect_job_failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            BandwidthSyncStatus::clearRunning($this->tenantId);
        }
    }

    public function failed(\Throwable $exception): void
    {
        BandwidthSyncStatus::clearRunning($this->tenantId);
    }
}
