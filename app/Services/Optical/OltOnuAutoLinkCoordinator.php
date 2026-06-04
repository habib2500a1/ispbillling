<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Services\Mikrotik\MikrotikCustomerVlanSyncService;
use App\Services\Network\OltFdbMacBridgeService;
use Illuminate\Support\Facades\Log;

/**
 * After any OLT SNMP inventory sync: FDB MAC bridge → PPP/MAC/login link → optional MikroTik VLAN.
 */
final class OltOnuAutoLinkCoordinator
{
    public function __construct(
        private readonly OltFdbMacBridgeService $fdbBridge,
        private readonly LegacyPortalOnuAutoLinkService $autoLink,
    ) {}

    /**
     * @return array{fdb?: array<string, mixed>, auto_link: array<string, mixed>, vlan_sync?: array<string, int>}
     */
    public function runAfterOltInventory(Device $olt): array
    {
        if (! config('optical.auto_link_on_olt_sync', config('optical.auto_link_on_bdcom_sync', true))) {
            return ['auto_link' => ['linked' => 0, 'skipped' => true]];
        }

        $tenantId = (int) $olt->tenant_id;
        $out = ['auto_link' => []];

        if ($this->fdbBridge->fdbEnabledFor($olt)) {
            $fdb = $this->fdbBridge->collectForOlt($olt);
            $out['fdb'] = $fdb;
            if (! ($fdb['success'] ?? false) && filled($fdb['error'] ?? null)) {
                Log::info('olt_onu_auto_link.fdb_skipped', [
                    'olt_id' => $olt->id,
                    'error' => $fdb['error'],
                ]);
            }
        }

        $out['auto_link'] = $this->autoLink->runAfterOltSync($tenantId);

        if (config('mikrotik.auto_sync_vlan', true)) {
            try {
                $out['vlan_sync'] = app(MikrotikCustomerVlanSyncService::class)
                    ->syncTenant($tenantId);
            } catch (\Throwable $e) {
                Log::warning('olt_onu_auto_link.vlan_sync_failed', [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $out;
    }
}
