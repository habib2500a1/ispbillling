<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Bandwidth\BandwidthCollectionService;
use Illuminate\Console\Command;

class CollectBandwidthUsageCommand extends Command
{
    protected $signature = 'isp:collect-bandwidth {--tenant= : Limit to tenant id}';

    protected $description = 'Merge MikroTik API + FreeRADIUS radacct: live sessions, bandwidth samples, daily usage, abuse checks.';

    public function handle(BandwidthCollectionService $collector): int
    {
        if (! config('bandwidth.collection_enabled', true)) {
            $this->info('Bandwidth collection disabled — clearing stale PPP online flags.');

            $tenantIds = $this->option('tenant')
                ? [(int) $this->option('tenant')]
                : Tenant::query()->pluck('id')->all();

            foreach ($tenantIds as $tenantId) {
                $collector->refreshOnlineFlagsForTenant((int) $tenantId);
            }

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
                'Tenant #%d: API %d · matched %d · online %d · samples %d · closed %d · api_ok=%s',
                $tenantId,
                $result['api_sessions'],
                $result['matched_subscribers'],
                $result['sessions_open'],
                $result['samples'],
                $result['sessions_closed'],
                $result['api_ok'] ? 'yes' : 'no',
            ));

            if ($result['api_errors'] !== []) {
                foreach ($result['api_errors'] as $err) {
                    $this->warn('  API: '.$err);
                }
            }

            if ($result['unmatched_logins'] !== []) {
                $this->warn('  Unmatched: '.implode(', ', array_slice($result['unmatched_logins'], 0, 10)));
            }
        }

        return self::SUCCESS;
    }
}
