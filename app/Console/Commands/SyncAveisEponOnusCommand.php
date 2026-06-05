<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Network\AveisGponOnuSyncService;
use Illuminate\Console\Command;

class SyncAveisEponOnusCommand extends Command
{
    protected $signature = 'isp:sync-aveis-epon-onus {--olt= : OLT device ID}';

    protected $description = 'SNMP sync ONUs from Aveis EPON OLT (enterprise 50224, MAC-registered ONUs)';

    public function handle(AveisGponOnuSyncService $sync): int
    {
        $query = Device::withoutGlobalScopes()->olts()->where('status', '!=', 'decommissioned');
        if ($id = $this->option('olt')) {
            $query->whereKey($id);
        }

        $olts = $query->get()->filter(function (Device $olt) use ($sync): bool {
            if (! $sync->supportsDriver($olt)) {
                return false;
            }

            $driver = strtolower((string) ($olt->olt_driver ?? ''));
            $profile = strtolower((string) ($olt->gpon_profile ?? ''));

            return $driver === 'aveis_epon' || $profile === 'aveis_epon';
        });

        if ($olts->isEmpty()) {
            $this->warn('No Aveis EPON OLTs found. Set OLT type = Aveis EPON and SNMP community.');

            return self::SUCCESS;
        }

        foreach ($olts as $olt) {
            $this->line("Syncing Aveis EPON OLT #{$olt->id} {$olt->adminLabel()} …");
            $result = $sync->syncOlt($olt, true);
            if ($result['success']) {
                $fdb = (int) ($result['fdb_macs_stored'] ?? 0);
                $this->info("  OK — {$result['discovered']} ONUs · +{$result['created']} new · {$result['updated']} updated · linked {$result['linked']} · FDB MACs {$fdb}");
            } else {
                $this->error('  FAIL — '.($result['error'] ?? 'unknown'));
            }
        }

        return self::SUCCESS;
    }
}
