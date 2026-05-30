<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Network\OltFdbMacBridgeService;
use App\Services\Optical\IspDigitalOnuAutoLinkService;
use App\Services\Optical\OnuSignalCollectionService;
use Illuminate\Console\Command;

/**
 * Detect the subscriber behind each ONU from the OLT forwarding table (customer MAC → PPPoE caller_id).
 * --dry previews matches without linking; default collects + links and refreshes signals.
 */
class DetectOnuSubscribersCommand extends Command
{
    protected $signature = 'isp:detect-onu-subscribers {--tenant= : Tenant id} {--olt= : Limit to one OLT device id} {--dry : Preview matches without linking}';

    protected $description = 'Auto-detect ONU → subscriber from the OLT FDB (router MAC behind each ONU == PPPoE caller_id)';

    public function handle(OltFdbMacBridgeService $bridge, IspDigitalOnuAutoLinkService $autoLink): int
    {
        $query = Device::withoutGlobalScopes()->where('type', 'olt')->where('status', '!=', 'decommissioned');
        if ($tenant = $this->option('tenant')) {
            $query->where('tenant_id', (int) $tenant);
        }
        if ($oltId = $this->option('olt')) {
            $query->whereKey($oltId);
        }

        $olts = $query->get()->filter(fn (Device $olt): bool => $bridge->supportsDriver($olt));
        if ($olts->isEmpty()) {
            $this->warn('No FDB-capable OLTs found (BDCOM EPON). Set OLT driver = bdcom_epon + SNMP community.');

            return self::SUCCESS;
        }

        $tenantIds = [];
        foreach ($olts as $olt) {
            $this->line("FDB walk on OLT #{$olt->id} {$olt->adminLabel()} …");
            $res = $bridge->collectForOlt($olt);
            if ($res['success']) {
                $this->info("  OK — {$res['fdb_entries']} FDB entries · {$res['onus_with_macs']} ONUs · {$res['macs_stored']} MACs stored");
                $tenantIds[(int) $olt->tenant_id] = true;
            } else {
                $this->error('  FAIL — '.($res['error'] ?? 'unknown'));
            }
        }

        foreach (array_keys($tenantIds) as $tenantId) {
            if ($this->option('dry')) {
                $matches = $autoLink->previewFdbMatches($tenantId);
                $fresh = array_filter($matches, fn (array $m): bool => ! $m['already_linked']);
                $this->newLine();
                $this->info("Tenant {$tenantId}: ".count($matches).' matched ONUs ('.count($fresh).' new, would link):');
                $this->table(
                    ['ONU', 'Customer MAC', 'PPP login', 'Status'],
                    array_map(fn (array $m): array => [
                        $m['onu'], $m['mac'], $m['login'], $m['already_linked'] ? 'linked' : 'NEW',
                    ], array_slice($matches, 0, 50)),
                );
                if (count($matches) > 50) {
                    $this->comment('  … '.(count($matches) - 50).' more not shown.');
                }

                continue;
            }

            $linked = $autoLink->linkByOltFdbMacs($tenantId);
            app(OnuSignalCollectionService::class)->collectForTenant($tenantId);
            $this->info("Tenant {$tenantId}: linked {$linked} ONUs to subscribers via OLT FDB.");
        }

        return self::SUCCESS;
    }
}
