<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Tenant;
use App\Services\Network\OltFdbMacBridgeService;
use App\Services\Optical\LegacyPortalOnuAutoLinkService;
use App\Services\Optical\OltOnuAutoLinkCoordinator;
use Illuminate\Console\Command;

/**
 * Full automatic ONU ↔ subscriber link (FDB + PPP + login/description + MikroTik VLAN).
 */
class AutoLinkOpticalCommand extends Command
{
    protected $signature = 'isp:auto-link-optical
                            {--tenant= : Tenant id}
                            {--olt= : Run FDB walk on one OLT first}';

    protected $description = 'Auto-link ONUs to subscribers (OLT FDB MAC + PPP + Aveis/BDCOM description + VLAN sync)';

    public function handle(OltOnuAutoLinkCoordinator $coordinator, OltFdbMacBridgeService $fdb): int
    {
        if (! config('optical.auto_link_on_olt_sync', config('optical.auto_link_on_bdcom_sync', true))) {
            $this->warn('Auto-link disabled (OPTICAL_AUTO_LINK_ON_OLT_SYNC=false).');

            return self::SUCCESS;
        }

        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            $this->info("Tenant #{$tenantId}: auto-link optical…");

            if ($oltId = $this->option('olt')) {
                $olt = Device::withoutGlobalScopes()->find($oltId);
                if ($olt !== null) {
                    $out = $coordinator->runAfterOltInventory($olt);
                    $this->printStats($out);
                }
            } else {
                $oltQuery = Device::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'olt')
                    ->where('status', '!=', 'decommissioned');

                foreach ($oltQuery->get() as $olt) {
                    if ($fdb->fdbEnabledFor($olt)) {
                        $fdbRes = $fdb->collectForOlt($olt);
                        if ($fdbRes['success']) {
                            $this->line("  FDB OLT #{$olt->id}: {$fdbRes['macs_stored']} MACs on {$fdbRes['onus_with_macs']} ONUs");
                        }
                    }
                }

                $al = app(LegacyPortalOnuAutoLinkService::class)->runAfterOltSync($tenantId);
                $this->printStats(['auto_link' => $al]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $out
     */
    private function printStats(array $out): void
    {
        $al = $out['auto_link'] ?? [];
        if (isset($out['fdb']['macs_stored'])) {
            $this->line('  FDB MACs stored: '.(int) $out['fdb']['macs_stored']);
        }
        $this->line(sprintf(
            '  Linked total: %d · FDB %d · PPP %d · hints %d · smart %d',
            (int) ($al['linked'] ?? 0),
            (int) ($al['fdb_linked'] ?? 0),
            (int) (($al['ppp_customer_linked'] ?? 0) + ($al['ppp_session_linked'] ?? 0)),
            (int) ($al['hint_linked'] ?? 0),
            (int) ($al['smart_linked'] ?? 0),
        ));
        if (isset($out['vlan_sync']['updated'])) {
            $this->line('  MikroTik VLAN updated: '.(int) $out['vlan_sync']['updated']);
        }
    }
}
