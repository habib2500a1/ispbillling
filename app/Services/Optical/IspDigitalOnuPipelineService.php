<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Network\BdcomEponOnuSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * ISP Digital–style flow: OLT SNMP → inventory → PPP login match → subscriber optical panel.
 */
final class IspDigitalOnuPipelineService
{
    public function __construct(
        private readonly BdcomEponOnuSyncService $bdcomSync,
        private readonly OnuSignalCollectionService $signalCollector,
    ) {}

    /**
     * Full tenant sync (cron): all BDCOM OLTs → smart link → signal snapshots.
     *
     * @return array{olts: int, discovered: int, linked: int, signals: array<string, int>}
     */
    public function runTenantPipeline(int $tenantId): array
    {
        $stats = ['olts' => 0, 'discovered' => 0, 'linked' => 0, 'signals' => []];

        foreach ($this->bdcomOltsForTenant($tenantId) as $olt) {
            $stats['olts']++;
            $result = $this->bdcomSync->syncOlt($olt, false);
            if ($result['success']) {
                $stats['discovered'] += (int) ($result['discovered'] ?? 0);
                $stats['linked'] += (int) ($result['linked'] ?? 0);
            } else {
                Log::warning('isp_digital_onu.sync_olt_failed', [
                    'olt_id' => $olt->id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
            }
        }

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
            $this->syncAllBdcomOlts($tenantId);
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
    public function syncAllBdcomOlts(int $tenantId): array
    {
        $out = ['synced' => 0, 'discovered' => 0, 'linked' => 0];

        foreach ($this->bdcomOltsForTenant($tenantId) as $olt) {
            $result = $this->bdcomSync->syncOlt($olt, false);
            if ($result['success']) {
                $out['synced']++;
                $out['discovered'] += (int) ($result['discovered'] ?? 0);
                $out['linked'] += (int) ($result['linked'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Device>
     */
    private function bdcomOltsForTenant(int $tenantId)
    {
        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->olts()
            ->where('status', '!=', 'decommissioned')
            ->orderBy('id')
            ->get()
            ->filter(fn (Device $olt): bool => $this->bdcomSync->supportsDriver($olt));
    }
}
