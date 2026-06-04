<?php

namespace App\Jobs;

use App\Services\Bandwidth\BandwidthCollectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshWanLiveSamplesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 90;

    public int $uniqueFor = 4;

    public function __construct(
        public int $tenantId,
        public bool $force = false,
    ) {
        $this->onQueue((string) config('bandwidth.queue_name', 'bandwidth'));
    }

    public function uniqueId(): string
    {
        return 'refresh-wan-tenant-'.$this->tenantId;
    }

    public function handle(BandwidthCollectionService $collector): void
    {
        if (! config('bandwidth.collection_enabled', true)) {
            return;
        }

        try {
            $collector->refreshWanLiveSamples($this->tenantId, $this->force);
        } catch (\Throwable $e) {
            Log::warning('bandwidth.wan_refresh_job_failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
