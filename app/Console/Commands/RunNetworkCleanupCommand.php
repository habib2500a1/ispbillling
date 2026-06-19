<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Network\NetworkCleanupWorkflowService;
use Illuminate\Console\Command;

class RunNetworkCleanupCommand extends Command
{
    protected $signature = 'isp:network-cleanup {--tenant= : Limit to tenant id}';

    protected $description = 'Safe network cleanup: suspended (24h+) or terminated (30d+) — never on simple ONU offline';

    public function handle(NetworkCleanupWorkflowService $cleanup): int
    {
        if (! config('network_cleanup.enabled', true)) {
            $this->info('Network cleanup disabled.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');

        $query = Tenant::query()->where('is_active', true);
        if ($tenantId) {
            $query->where('id', (int) $tenantId);
        }

        $query->each(function (Tenant $tenant) use ($cleanup): void {
            $stats = $cleanup->runForTenant($tenant->id);
            $this->line(sprintf(
                'Tenant %d: suspended=%d terminated=%d',
                $tenant->id,
                $stats['suspended'],
                $stats['terminated'],
            ));
        });

        return self::SUCCESS;
    }
}
