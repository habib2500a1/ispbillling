<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Mikrotik\MikrotikCustomerVlanSyncService;
use App\Services\Mikrotik\MikrotikPonPortLabelSyncService;
use Illuminate\Console\Command;

class SyncMikrotikVlanCommand extends Command
{
    protected $signature = 'isp:sync-mikrotik-vlan
                            {--tenant= : Tenant id (default: all tenants with MikroTik)}
                            {--server= : MikroTik server id}';

    protected $description = 'Sync VLAN ids from MikroTik PPP secrets into subscriber meta (for NOC / PON tables).';

    public function handle(MikrotikCustomerVlanSyncService $sync, MikrotikPonPortLabelSyncService $ponLabels): int
    {
        if (! config('mikrotik.auto_sync_vlan', true)) {
            $this->warn('MikroTik VLAN auto-sync is disabled (MIKROTIK_AUTO_SYNC_VLAN=false).');

            return self::SUCCESS;
        }

        $serverId = $this->option('server') !== null ? (int) $this->option('server') : null;
        $tenantOption = $this->option('tenant');

        $tenantIds = [];
        if ($tenantOption !== null) {
            $tenantIds[] = (int) $tenantOption;
        } else {
            $tenantIds = Tenant::query()->withoutGlobalScopes()->orderBy('id')->pluck('id')->all();
        }

        $totalUpdated = 0;
        $totalMatched = 0;

        foreach ($tenantIds as $tenantId) {
            $result = $sync->syncTenant((int) $tenantId, $serverId);
            $totalUpdated += $result['updated'];
            $totalMatched += $result['matched'];

            if ($result['updated'] > 0 || $result['matched'] > 0) {
                $this->line("Tenant {$tenantId}: matched {$result['matched']}, updated {$result['updated']}, skipped {$result['skipped']}");
            }

            foreach (array_slice($result['errors'], 0, 5) as $err) {
                $this->error($err);
            }
        }

        $this->info("VLAN sync done — {$totalUpdated} subscriber(s) updated ({$totalMatched} matched on router).");

        if (config('mikrotik.auto_sync_pon_port_names', true)) {
            $portsUpdated = 0;
            $portsSeen = 0;
            foreach ($tenantIds as $tenantId) {
                $pon = $ponLabels->syncTenant((int) $tenantId);
                $portsUpdated += $pon['ports_updated'];
                $portsSeen += $pon['ports_seen'];
                if ($pon['ports_seen'] > 0) {
                    $this->line("Tenant {$tenantId}: PON ports with MikroTik interface — {$pon['ports_seen']} mapped, {$pon['ports_updated']} label(s) updated.");
                }
            }
            if ($portsSeen === 0) {
                $this->comment('PON port names: no ONU↔subscriber link with mikrotik_interface (link ONU + run VLAN sync).');
            }
        }

        return self::SUCCESS;
    }
}
