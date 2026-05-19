<?php

namespace App\Services\Network;

use App\Models\Device;

class GponIntelligenceService
{
    public function resolveProfile(Device $olt): string
    {
        if (filled($olt->gpon_profile)) {
            return (string) $olt->gpon_profile;
        }

        $driver = (string) ($olt->olt_driver ?? '');
        $mapped = config("gpon.driver_to_profile.{$driver}");

        return is_string($mapped) ? $mapped : (string) config('gpon.default_profile', 'generic_gpon');
    }

    /**
     * @return array<string, string>
     */
    public function oidsForProfile(string $profile): array
    {
        $profiles = config('gpon.profiles', []);
        $cfg = $profiles[$profile] ?? $profiles['generic_gpon'] ?? [];

        if (isset($cfg['extends'])) {
            $parent = $profiles[$cfg['extends']] ?? [];
            $cfg = array_merge($parent, $cfg);
        }

        return array_filter([
            'sys_descr' => $cfg['sys_descr'] ?? '1.3.6.1.2.1.1.1.0',
            'sys_uptime' => $cfg['sys_uptime'] ?? '1.3.6.1.2.1.1.3.0',
            'if_oper_status' => $cfg['if_oper_status'] ?? '1.3.6.1.2.1.2.2.1.8',
        ]);
    }

    public function syncOnuOpticalFromMeta(Device $onu): bool
    {
        if ($onu->type !== 'onu') {
            return false;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $keys = config('gpon.onu_meta_keys', []);
        $updated = false;

        foreach (['rx_power_dbm' => 'rx_power_dbm', 'tx_power_dbm' => 'tx_power_dbm'] as $column => $configKey) {
            $aliases = $keys[$configKey] ?? [];
            foreach ($aliases as $alias) {
                if (isset($meta[$alias]) && $meta[$alias] !== '') {
                    $onu->{$column} = is_numeric($meta[$alias]) ? (float) $meta[$alias] : $onu->{$column};
                    $updated = true;
                    break;
                }
            }
        }

        $statusAliases = $keys['onu_oper_status'] ?? [];
        foreach ($statusAliases as $alias) {
            if (isset($meta[$alias]) && $meta[$alias] !== '') {
                $onu->onu_oper_status = strtolower((string) $meta[$alias]);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $onu->last_polled_at = now();
            $onu->save();
        }

        return $updated;
    }

    /**
     * @return array{synced: int, total: int}
     */
    public function syncAllOnuOpticalForOlt(Device $olt): array
    {
        $synced = 0;
        $onus = $olt->onus()->get();
        foreach ($onus as $onu) {
            if ($this->syncOnuOpticalFromMeta($onu)) {
                $synced++;
            }
        }

        return ['synced' => $synced, 'total' => $onus->count()];
    }
}
