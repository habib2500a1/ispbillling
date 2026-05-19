<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Network\GponIntelligenceService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Services\Optical\OnuSignalCollectionService;
use Illuminate\Console\Command;

class PollOltIntelligenceCommand extends Command
{
    protected $signature = 'isp:poll-olt-intelligence {--olt= : OLT device ID}';

    protected $description = 'SNMP poll OLTs (GPON/IF-MIB) and sync ONU optical data from meta';

    public function handle(OltSnmpMonitorService $monitor, GponIntelligenceService $gpon, OnuSignalCollectionService $optical): int
    {
        if (! config('network.olt_snmp_poll_enabled', true)) {
            $this->warn('OLT SNMP polling disabled (NETWORK_OLT_SNMP_POLL_ENABLED=false).');

            return self::SUCCESS;
        }

        $query = Device::withoutGlobalScopes()->olts()->where('status', '!=', 'decommissioned');
        if ($id = $this->option('olt')) {
            $query->whereKey($id);
        }

        $olts = $query->get();

        if (config('optical.auto_provision_customer_onu', true)
            && ! config('sync.skip_provision_in_olt_poll', true)) {
            $provision = app(CustomerOnuAutoProvisionService::class);
            foreach ($olts->pluck('tenant_id')->unique() as $tenantId) {
                $stats = $provision->provisionMissingForTenant((int) $tenantId, 500);
                if ($stats['created'] > 0 || $stats['linked'] > 0) {
                    $this->line("ONU inventory: tenant #{$tenantId} — created {$stats['created']}, linked {$stats['linked']}");
                }
            }
        }

        $ok = 0;
        $fail = 0;

        foreach ($olts as $olt) {
            $result = $monitor->pollOlt($olt);
            $sync = ['synced' => 0];
            if (! config('sync.skip_gpon_meta_in_olt_poll', true)) {
                $sync = $gpon->syncAllOnuOpticalForOlt($olt->fresh());
            }
            if ($result['success']) {
                $ok++;
                $this->line("OK  OLT #{$olt->id} {$olt->adminLabel()} — ONUs {$result['onus_online']}/".($result['onus_online'] + $result['onus_offline']).", meta sync {$sync['synced']}");
            } else {
                $fail++;
                $this->warn("FAIL OLT #{$olt->id}: ".($result['error'] ?? 'unknown'));
            }
        }

        $this->info("Polled {$olts->count()} OLT(s): {$ok} OK, {$fail} failed.");

        if (config('optical.enabled', true) && ! config('sync.skip_optical_in_olt_poll', true)) {
            foreach ($olts->pluck('tenant_id')->unique() as $tenantId) {
                $optical->collectForTenant((int) $tenantId);
            }
            $this->line('Optical signal history updated.');
        }

        return self::SUCCESS;
    }
}
