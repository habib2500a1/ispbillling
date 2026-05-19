<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\OnuSignalCollectionService;
use Illuminate\Console\Command;

class CollectOnuSignalsCommand extends Command
{
    protected $signature = 'isp:collect-onu-signals {--tenant= : Tenant id}';

    protected $description = 'Sync ONU RX/TX from meta, log signal history, health scores, alerts, and PON port stats.';

    public function handle(OnuSignalCollectionService $collector): int
    {
        if (! config('optical.enabled', true)) {
            $this->warn('Optical monitoring disabled (OPTICAL_MONITORING_ENABLED=false).');

            return self::SUCCESS;
        }

        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        if ($tenantIds === []) {
            $tenantIds = [1];
        }

        foreach ($tenantIds as $tenantId) {
            $result = $collector->collectForTenant((int) $tenantId);
            $this->info(sprintf(
                'Tenant #%d: %d ONUs · %d snapshots · %d new alerts',
                $tenantId,
                $result['onus'],
                $result['logged'],
                $result['alerts'],
            ));
        }

        return self::SUCCESS;
    }
}
