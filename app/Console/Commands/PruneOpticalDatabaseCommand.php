<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\OpticalDatabaseMaintenanceService;
use Illuminate\Console\Command;

class PruneOpticalDatabaseCommand extends Command
{
    protected $signature = 'isp:prune-optical-database {--tenant= : Tenant id}';

    protected $description = 'Prune old optical history (signal logs, OLT health, resolved alerts) per retention config';

    public function handle(OpticalDatabaseMaintenanceService $db): int
    {
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        if ($tenantIds === []) {
            $tenantIds = [1];
        }

        foreach ($tenantIds as $tenantId) {
            $deleted = $db->prune((int) $tenantId);
            $this->info(sprintf(
                'Tenant #%d: snapshots %d · hourly %d · olt_health %d · snmp %d · alerts %d · predictions %d',
                $tenantId,
                $deleted['snapshots'],
                $deleted['hourly'],
                $deleted['olt_health'],
                $deleted['snmp_polls'],
                $deleted['resolved_alerts'],
                $deleted['predictions'],
            ));
        }

        return self::SUCCESS;
    }
}
