<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Network\BdcomEponOnuSyncService;
use Illuminate\Console\Command;

class SyncBdcomEponOnusCommand extends Command
{
    protected $signature = 'isp:sync-bdcom-epon-onus {--olt= : OLT device ID} {--delete-offline : Remove offline/LOS rows from inventory after sync}';

    protected $description = 'SNMP sync all ONUs from BDCOM EPON OLT (MAC, RX/TX, status)';

    public function handle(BdcomEponOnuSyncService $sync): int
    {
        $query = Device::withoutGlobalScopes()->olts()->where('status', '!=', 'decommissioned');
        if ($id = $this->option('olt')) {
            $query->whereKey($id);
        }

        $olts = $query->get()->filter(fn (Device $olt): bool => $sync->supportsDriver($olt));
        if ($olts->isEmpty()) {
            $this->warn('No BDCOM EPON OLTs found.');

            return self::SUCCESS;
        }

        $deleteOffline = (bool) $this->option('delete-offline');

        foreach ($olts as $olt) {
            $this->line("Syncing OLT #{$olt->id} {$olt->adminLabel()} …");
            $result = $sync->syncOlt($olt, $deleteOffline);
            if ($result['success']) {
                $linked = isset($result['linked']) ? ", linked subscribers {$result['linked']}" : '';
                $this->info("  OK — discovered {$result['discovered']}, created {$result['created']}, updated {$result['updated']}, deleted offline {$result['deleted_offline']}{$linked}");
            } else {
                $this->error('  FAIL — '.($result['error'] ?? 'unknown'));
            }
        }

        return self::SUCCESS;
    }
}
