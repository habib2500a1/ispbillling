<?php

namespace App\Support;

use App\Models\Device;

/**
 * Resolve merged gpon.profiles.* OID map for an OLT driver / gpon_profile.
 */
final class GponSnmpProfile
{
    /**
     * @return array<string, mixed>
     */
    public static function forOlt(Device $olt): array
    {
        return self::merged(self::profileKeyForOlt($olt));
    }

    public static function profileKeyForOlt(Device $olt): string
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $map = config('gpon.driver_to_profile', []);

        return (string) ($map[$driver] ?? $olt->gpon_profile ?? $driver ?: config('gpon.default_profile', 'generic_gpon'));
    }

    /**
     * Config-driven SNMP sync (VSOL-style OID walks) — any listed driver or vendor alias.
     */
    public static function isConfigDrivenDriver(Device $olt): bool
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));
        $vendor = strtolower((string) ($olt->vendor ?? ''));

        if (in_array($driver, self::configDrivenDrivers(), true)) {
            return $driver !== 'generic_snmp' || self::hasOnuOids($olt);
        }

        if (in_array($profile, self::configDrivenProfiles(), true)) {
            return $profile !== 'generic_gpon' || self::hasOnuOids($olt);
        }

        return in_array($vendor, self::configDrivenVendors(), true);
    }

    /**
     * @return list<string>
     */
    public static function configDrivenDrivers(): array
    {
        return array_values(array_unique(array_map(
            'strtolower',
            (array) config('gpon.config_driven_drivers', []),
        )));
    }

    /**
     * @return list<string>
     */
    public static function configDrivenProfiles(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $d): ?string => config("gpon.driver_to_profile.{$d}"),
            self::configDrivenDrivers(),
        ))));
    }

    /**
     * @return list<string>
     */
    public static function configDrivenVendors(): array
    {
        return array_values(array_unique((array) config('gpon.config_driven_vendors', [])));
    }

    public static function hasOnuOids(Device $olt): bool
    {
        $oids = self::onuOids($olt);

        return $oids['desc'] !== '' || $oids['mac'] !== '' || $oids['sn'] !== '' || $oids['rx'] !== '';
    }

    /**
     * Normalized ONU SNMP OIDs: per-OLT meta.snmp_onu_oids overrides profile / .env.
     *
     * @return array{
     *   desc: string,
     *   status: string,
     *   mac: string,
     *   rx: string,
     *   tx: string,
     *   temp: string,
     *   voltage: string,
     *   sn: string,
     *   optical_scale: int,
     * }
     */
    public static function onuOids(Device $olt): array
    {
        $profile = self::forOlt($olt);
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $custom = is_array($meta['snmp_onu_oids'] ?? null) ? $meta['snmp_onu_oids'] : [];

        $pick = static function (string $customKey, string $profileKey, string $envKey = '') use ($custom, $profile): string {
            $fromMeta = trim((string) ($custom[$customKey] ?? $custom[str_replace('onu_', '', $customKey)] ?? ''));
            if ($fromMeta !== '') {
                return $fromMeta;
            }

            $fromProfile = trim((string) ($profile[$profileKey] ?? ''));
            if ($fromProfile !== '') {
                return $fromProfile;
            }

            if ($envKey !== '') {
                return trim((string) env($envKey, ''));
            }

            return '';
        };

        return [
            'desc' => $pick('desc', 'vsol_onu_desc', 'VSOL_SNMP_ONU_DESC_OID'),
            'status' => $pick('status', 'vsol_onu_status', 'VSOL_SNMP_ONU_STATUS_OID'),
            'mac' => $pick('mac', 'vsol_onu_mac', 'VSOL_SNMP_ONU_MAC_OID'),
            'rx' => $pick('rx', 'vsol_onu_rx', 'VSOL_SNMP_ONU_RX_OID'),
            'tx' => $pick('tx', 'vsol_onu_tx', 'VSOL_SNMP_ONU_TX_OID'),
            'temp' => $pick('temp', 'vsol_onu_temp', 'VSOL_SNMP_ONU_TEMP_OID'),
            'voltage' => $pick('voltage', 'vsol_onu_voltage', 'VSOL_SNMP_ONU_VOLTAGE_OID'),
            'sn' => $pick('sn', 'vsol_onu_sn', 'VSOL_SNMP_ONU_SN_OID'),
            'optical_scale' => max(1, (int) ($custom['optical_scale'] ?? $profile['vsol_optical_scale'] ?? config('gpon.vsol_optical_scale', 10))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function merged(string $profileKey): array
    {
        $profiles = config('gpon.profiles', []);
        $cfg = $profiles[$profileKey] ?? $profiles['generic_gpon'] ?? [];
        $seen = [];

        while (isset($cfg['extends']) && ! isset($seen[$cfg['extends']])) {
            $parentKey = (string) $cfg['extends'];
            $seen[$parentKey] = true;
            $parent = $profiles[$parentKey] ?? [];
            $cfg = array_merge($parent, $cfg);
        }

        unset($cfg['extends'], $cfg['label'], $cfg['notes'], $cfg['enterprise']);

        return $cfg;
    }
}
