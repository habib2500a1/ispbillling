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
            'error' => 'No SNMP ONU sync for «'.($olt->olt_driver ?? 'unset').'». Use BDCOM/Huawei/Aveis, or set VSOL_SNMP_ONU_* / meta.snmp_onu_oids for ZTE/VSOL/Ecom/C-DATA/Fiberhome.',
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

        if (str_contains($s, 'av-olt') || str_contains($s, 'aveis') || str_contains($s, 'xe08')) {
            return str_contains($s, 'epon') ? 'aveis_epon' : 'aveis_gpon';
        }

        if (str_contains($s, 'huawei') || str_contains($s, 'ma58') || str_contains($s, 'ma56') || str_contains($s, 'ma5600') || str_contains($s, 'ma5800')) {
            return 'huawei_gpon';
        }

        if (str_contains($s, 'bdcom') || str_contains($s, 'p33') || str_contains($s, 'p3608')) {
            return str_contains($s, 'epon') ? 'bdcom_epon' : 'bdcom_gpon';
        }

        if (str_contains($s, 'zte') || str_contains($s, 'c300') || str_contains($s, 'c320') || str_contains($s, 'zxa10') || str_contains($s, 'zxan')) {
            return str_contains($s, 'epon') ? 'zte_epon' : 'zte_gpon';
        }

        if (str_contains($s, 'fiberhome') || str_contains($s, 'an55') || str_contains($s, 'an5516')) {
            return 'fiberhome_gpon';
        }

        if (str_contains($s, 'nokia') || str_contains($s, 'alcatel') || str_contains($s, 'isam')) {
            return 'nokia_gpon';
        }

        if (str_contains($s, 'raisecom') || str_contains($s, 'rcios')) {
            return str_contains($s, 'epon') ? 'raisecom_epon' : 'raisecom_gpon';
        }

        if (str_contains($s, 'vsol') || str_contains($s, 'v-solution') || str_contains($s, 'v1600')
            || str_contains($s, 'v280') || str_contains($s, 'v3600')) {
            return 'vsol_gpon';
        }

        if (str_contains($s, 'ecom') || str_contains($s, 'ec-olt')) {
            return str_contains($s, 'epon') ? 'ecom_epon' : 'ecom_gpon';
        }

        if (str_contains($s, 'c-data') || str_contains($s, 'cdata') || str_contains($s, 'fd11')) {
            return 'cdata_gpon';
        }

        return null;
    }

    /**
     * Apply sysDescr-based driver when enabled and current driver is overwritable.
     */
    public static function applyDriverFromSysDescr(Device $olt, ?string $sysDescr): bool
    {
        if (! config('gpon.auto_driver_from_snmp', true)) {
            return false;
        }

        $guessed = self::guessDriverFromSysDescr($sysDescr);
        if ($guessed === null) {
            return false;
        }

        $current = strtolower((string) ($olt->olt_driver ?? ''));
        $overwritable = array_map('strtolower', (array) config('gpon.auto_driver_overwritable', []));

        if ($current !== '' && $current === $guessed) {
            return false;
        }

        if ($current !== '' && ! in_array($current, $overwritable, true)) {
            return false;
        }

        $olt->forceFill([
            'olt_driver' => $guessed,
            'vendor' => config("olt_drivers.drivers.{$guessed}.vendor") ?? $olt->vendor,
            'gpon_profile' => config("gpon.driver_to_profile.{$guessed}") ?? $olt->gpon_profile,
        ])->saveQuietly();

        return true;
    }
}
