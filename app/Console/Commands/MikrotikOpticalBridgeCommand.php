<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Optical\MikrotikOpticalBridgeService;
use App\Support\TenantResolver;
use Illuminate\Console\Command;

final class MikrotikOpticalBridgeCommand extends Command
{
    protected $signature = 'isp:mikrotik-optical-bridge
                            {--tenant=1 : Tenant id}
                            {--limit=200 : Max subscribers}
                            {--link : Also search OLT and link ONU}';

    protected $description = 'Pull EPON/MAC hints from MikroTik PPP secrets, optionally link OLT ONUs';

    public function handle(MikrotikOpticalBridgeService $bridge): int
    {
        $tenantId = (int) $this->option('tenant');
        TenantResolver::fake($tenantId);

        $limit = max(1, (int) $this->option('limit'));
        $doLink = (bool) $this->option('link');

        $customers = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('mikrotik_server_id')
            ->whereDoesntHave('devices', fn ($q) => $q->where('type', 'onu')->whereNotNull('rx_power_dbm'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $hintsUpdated = 0;
        $linked = 0;

        foreach ($customers as $customer) {
            $result = $bridge->syncHintsFromMikrotik($customer);
            if ($result['updated']) {
                $hintsUpdated++;
            }

            if ($doLink) {
                $onu = $bridge->syncAndLinkFromMikrotik($customer->fresh(), false);
                if ($onu !== null) {
                    $linked++;
                    $this->line("Linked {$customer->pppLoginName()} → {$onu->display_name}");
                }
            }
        }

        $this->info("Hints updated: {$hintsUpdated}, linked: {$linked} (processed {$customers->count()})");

        return self::SUCCESS;
    }
}
