<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Network\AveisGponOnuSyncService;
use Illuminate\Console\Command;

class SyncAveisGponOnusCommand extends Command
{
    protected $signature = 'isp:sync-aveis-gpon-onus {--olt= : OLT device ID}';

    protected $description = 'SNMP sync ONUs from Aveis OLT (AV-OLT-XE08, enterprise 50224)';

    public function handle(AveisGponOnuSyncService $sync): int
    {
        $query = Device::withoutGlobalScopes()->olts()->where('status', '!=', 'decommissioned');
        if ($id = $this->option('olt')) {
            $query->whereKey($id);
        }

        $olts = $query->get()->filter(fn (Device $olt): bool => $sync->supportsDriver($olt));
        if ($olts->isEmpty()) {
            $this->warn('No Aveis OLTs found. Set OLT type = Aveis GPON and SNMP community.');

            return self::SUCCESS;
        }

        foreach ($olts as $olt) {
            $this->line("Syncing Aveis OLT #{$olt->id} {$olt->adminLabel()} …");
            $result = $sync->syncOlt($olt, true);
            if ($result['success']) {
                $this->info("  OK — {$result['discovered']} ONUs · +{$result['created']} new · {$result['updated']} updated · linked {$result['linked']}");
            } else {
                $this->error('  FAIL — '.($result['error'] ?? 'unknown'));
            }
        }

        return self::SUCCESS;
    }
}
