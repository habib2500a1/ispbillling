<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Network\OltFdbMacBridgeService;
use App\Services\Network\OltOnuSyncCoordinator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ISP Digital–style flow: OLT SNMP → inventory → PPP login match → subscriber optical panel.
 */
final class IspDigitalOnuPipelineService
{
    public function __construct(
        private readonly OltOnuSyncCoordinator $oltSync,
        private readonly OnuSignalCollectionService $signalCollector,
        private readonly IspDigitalOnuAutoLinkService $autoLink,
        private readonly OltFdbMacBridgeService $fdbBridge,
    ) {}

    /**
     * Full tenant sync (cron): all BDCOM OLTs → smart link → signal snapshots.
     *
     * @return array{olts: int, discovered: int, linked: int, auto_link: array<string, int>, signals: array<string, int>}
     */
    public function runTenantPipeline(int $tenantId): array
    {
        $stats = ['olts' => 0, 'discovered' => 0, 'linked' => 0, 'auto_link' => [], 'signals' => []];

        $stats['fdb_macs_stored'] = 0;
        foreach ($this->oltSync->oltsForTenant($tenantId) as $olt) {
            $stats['olts']++;
            $result = $this->oltSync->syncOlt($olt, false);
            if ($result['success']) {
                $stats['discovered'] += (int) ($result['discovered'] ?? 0);

                // Learn the customer MACs behind each ONU from the OLT forwarding table so the
                // auto-linker can match them to PPPoE caller_id (all vendors; non-supporting no-op).
                if ($this->fdbBridge->fdbEnabledFor($olt)) {
                    $fdb = $this->fdbBridge->collectForOlt($olt);
                    $stats['fdb_macs_stored'] += (int) ($fdb['macs_stored'] ?? 0);
                    if (! $fdb['success'] && $fdb['error'] !== null) {
                        Log::warning('isp_digital_onu.fdb_bridge_failed', [
                            'olt_id' => $olt->id,
                            'error' => $fdb['error'],
                        ]);
                    }
                }
            } else {
                Log::warning('isp_digital_onu.sync_olt_failed', [
                    'olt_id' => $olt->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
            }
        }

        $stats['auto_link'] = $this->autoLink->runAfterOltSync($tenantId);
        $stats['linked'] = (int) ($stats['auto_link']['linked'] ?? 0);

        $stats['signals'] = $this->signalCollector->collectForTenant($tenantId);

        return $stats;
    }

    /**
     * Single subscriber: refresh OLT inventory if stale, then link + optical read.
     */
    public function syncAndLinkCustomer(Customer $customer, bool $forceOltSync = false): ?Device
    {
        if (! config('optical.enabled', true)) {
            return null;
        }

        $tenantId = (int) $customer->tenant_id;

        if (config('optical.mikrotik_optical_bridge_enabled', true)) {
            app(MikrotikOpticalBridgeService::class)->syncHintsFromMikrotik($customer);
            $customer = $customer->fresh();
        }

        if ($forceOltSync || ! $this->tenantInventoryFresh($tenantId)) {
            $this->syncAllOlts($tenantId);
        }

        if (config('optical.mikrotik_optical_bridge_enabled', true)) {
            $byEpon = CustomerOnuMatcher::findUnlinkedOnuForEponHints($tenantId, $customer);
            if ($byEpon !== null) {
                app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer(
                    $customer,
                    (int) $byEpon->id,
                    CustomerOnuSmartLinkService::REASON_EPON_EXACT,
                    92,
                );
                $customer->refresh();

                return $customer->primaryOnu();
            }
        }

        $byMac = CustomerOnuMatcher::linkCustomerByMacFromOlt($customer->fresh(), false);
        if ($byMac !== null) {
            $customer->refresh();

            return $customer->primaryOnu();
        }

        app(CustomerOnuAutoProvisionService::class)->autoFindAndLinkOnu($customer->fresh());

        $customer->refresh();
        $onu = $customer->primaryOnu();

        if ($onu !== null && ($onu->rx_power_dbm === null || $onu->last_polled_at === null)) {
            $this->signalCollector->collectForTenant($tenantId);
            $onu->refresh();
        }

        return $customer->fresh()->primaryOnu();
    }

    public function tenantInventoryFresh(int $tenantId, ?int $maxAgeSeconds = null): bool
    {
        $maxAgeSeconds ??= (int) config('optical.isp_digital_inventory_max_age_seconds', 180);

        $latest = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('meta')
            ->orderByDesc('last_polled_at')
            ->value('last_polled_at');

        if ($latest === null) {
            $row = Device::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('type', 'onu')
                ->whereNotNull('meta')
                ->first();

            if ($row === null) {
                return false;
            }

            $meta = is_array($row->meta) ? $row->meta : [];
            $synced = $meta['last_bdcom_sync'] ?? null;
            if ($synced === null) {
                return false;
            }

            try {
                return Carbon::parse((string) $synced)->greaterThan(now()->subSeconds($maxAgeSeconds));
            } catch (\Throwable) {
                return false;
            }
        }

        return Carbon::parse($latest)->greaterThan(now()->subSeconds($maxAgeSeconds));
    }

    /**
     * @return array{synced: int, discovered: int, linked: int}
     */
    public function syncAllOlts(int $tenantId): array
    {
        return $this->oltSync->syncAllForTenant($tenantId);
    }

    /** @deprecated Use syncAllOlts() */
    public function syncAllBdcomOlts(int $tenantId): array
    {
        return $this->syncAllOlts($tenantId);
    }
}
