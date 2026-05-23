<?php

namespace App\Services\Network;

use App\Models\Device;
use Illuminate\Support\Collection;

/**
 * Routes OLT SNMP inventory sync to the correct vendor driver (BDCOM, Huawei, Aveis, VSOL, …).
 */
final class OltOnuSyncCoordinator
{
    /** @var list<object{supportsDriver: callable, syncOlt: callable}> */
    private array $syncers;

    public function __construct(
        BdcomEponOnuSyncService $bdcomEpon,
        HuaweiGponOnuSyncService $huaweiGpon,
        AveisGponOnuSyncService $aveisGpon,
        VsolGponOnuSyncService $vsolGpon,
    ) {
        $this->syncers = [$bdcomEpon, $huaweiGpon, $aveisGpon, $vsolGpon];
    }

    /**
     * @return array{success: bool, discovered: int, created: int, updated: int, linked: int, error: ?string, driver: ?string}
     */
    public function syncOlt(Device $olt, bool $runSmartLink = false): array
    {
        foreach ($this->syncers as $syncer) {
            if (! $syncer->supportsDriver($olt)) {
                continue;
            }

            $driver = strtolower((string) ($olt->olt_driver ?? 'unknown'));
            $fresh = $olt->fresh();
            $result = match (true) {
                $syncer instanceof BdcomEponOnuSyncService => $syncer->syncOlt($fresh, false),
                $syncer instanceof HuaweiGponOnuSyncService => $syncer->syncOlt($fresh),
                default => $syncer->syncOlt($fresh, $runSmartLink),
            };
            $result['driver'] = $driver;

            return $result;
        }

        return [
            'success' => false,
            'discovered' => 0,
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'error' => 'No SNMP ONU sync driver for OLT type «'.($olt->olt_driver ?? 'unset').'». Pick Aveis / VSOL / Ecom / BDCOM / Huawei in OLT manage.',
            'driver' => null,
        ];
    }

    public function supportsOlt(Device $olt): bool
    {
        foreach ($this->syncers as $syncer) {
            if ($syncer->supportsDriver($olt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{synced: int, discovered: int, linked: int, errors: list<string>}
     */
    public function syncAllForTenant(int $tenantId): array
    {
        $out = ['synced' => 0, 'discovered' => 0, 'linked' => 0, 'errors' => []];

        foreach ($this->oltsForTenant($tenantId) as $olt) {
            $result = $this->syncOlt($olt, false);
            if ($result['success']) {
                $out['synced']++;
                $out['discovered'] += (int) ($result['discovered'] ?? 0);
                $out['linked'] += (int) ($result['linked'] ?? 0);
            } elseif (filled($result['error'])) {
                $out['errors'][] = sprintf('OLT #%d (%s): %s', $olt->id, $olt->management_ip, $result['error']);
            }
        }

        return $out;
    }

    /**
     * @return Collection<int, Device>
     */
    public function oltsForTenant(int $tenantId): Collection
    {
        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->olts()
            ->where('status', '!=', 'decommissioned')
            ->orderBy('id')
            ->get()
            ->filter(fn (Device $olt): bool => $this->supportsOlt($olt));
    }

    /**
     * Guess olt_driver from SNMP sysDescr (call after successful SNMP probe).
     */
    public static function guessDriverFromSysDescr(?string $sysDescr): ?string
    {
        if ($sysDescr === null || trim($sysDescr) === '') {
            return null;
        }

        $s = strtolower($sysDescr);

        if (str_contains($s, 'av-olt') || str_contains($s, 'aveis')) {
            return 'aveis_gpon';
        }

        if (str_contains($s, 'vsol') || str_contains($s, 'v1600') || str_contains($s, 'v280')) {
            return 'vsol_gpon';
        }

        if (str_contains($s, 'ecom') || str_contains($s, 'ec-olt')) {
            return 'ecom_gpon';
        }

        if (str_contains($s, 'huawei') || str_contains($s, 'ma58') || str_contains($s, 'ma56')) {
            return 'huawei_gpon';
        }

        if (str_contains($s, 'bdcom') || str_contains($s, 'p33')) {
            return str_contains($s, 'epon') ? 'bdcom_epon' : 'bdcom_gpon';
        }

        if (str_contains($s, 'zte') || str_contains($s, 'c300') || str_contains($s, 'c320')) {
            return str_contains($s, 'epon') ? 'zte_epon' : 'zte_gpon';
        }

        if (str_contains($s, 'fiberhome') || str_contains($s, 'an55')) {
            return 'fiberhome_gpon';
        }

        if (str_contains($s, 'c-data') || str_contains($s, 'cdata')) {
            return 'cdata_gpon';
        }

        return null;
    }
}
