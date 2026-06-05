<?php

namespace App\Filament\Resources\OltResource\Concerns;

use App\Models\Device;
use App\Support\OltManagementHelper;

trait NormalizesOltFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeOltFormData(array $data, ?Device $existingOlt = null): array
    {
        $data['type'] = 'olt';

        if (isset($data['management_ip'])) {
            $data['management_ip'] = OltManagementHelper::normalizeManagementIp((string) $data['management_ip']);
        }

        if (array_key_exists('is_active', $data)) {
            $data['status'] = ($data['is_active'] ?? false) ? 'active' : 'offline';
            unset($data['is_active']);
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $extraMeta = is_array($data['meta_extra'] ?? null) ? $data['meta_extra'] : [];
        $webPassword = $data['olt_web_password'] ?? null;
        $pptpPassword = $data['olt_pptp_password'] ?? null;
        unset($data['olt_web_password'], $data['olt_pptp_password']);

        $meta = OltManagementHelper::mergeWebCredentialsIntoMeta(
            $meta,
            isset($data['olt_web_url']) ? (string) $data['olt_web_url'] : null,
            isset($data['olt_web_username']) ? (string) $data['olt_web_username'] : null,
            is_string($webPassword) ? $webPassword : null,
        );
        unset($data['olt_web_url'], $data['olt_web_username']);

        $vpnType = isset($data['olt_vpn_type']) ? (string) $data['olt_vpn_type'] : null;
        $meta = OltManagementHelper::mergeVpnIntoMeta(
            $meta,
            $vpnType,
            null,
            isset($data['olt_pptp_server']) ? (string) $data['olt_pptp_server'] : null,
            isset($data['olt_pptp_username']) ? (string) $data['olt_pptp_username'] : null,
            is_string($pptpPassword) ? $pptpPassword : null,
            isset($data['olt_pptp_subnet']) ? (string) $data['olt_pptp_subnet'] : null,
        );
        unset(
            $data['olt_vpn_type'],
            $data['olt_pptp_server'],
            $data['olt_pptp_username'],
            $data['olt_pptp_subnet'],
            $data['olt_openvpn_config'],
        );
        unset($data['meta_extra']);

        $hiddenMetaKeys = OltManagementHelper::metaKeysHiddenFromExtraForm();

        // Keep advanced key/value metadata separate from structured meta.* form fields.
        if ($extraMeta !== []) {
            foreach ($extraMeta as $key => $value) {
                if (! is_string($key) || $key === '' || in_array($key, $hiddenMetaKeys, true)) {
                    continue;
                }

                if (is_string($value) && strcasecmp(trim($value), '[object Object]') === 0) {
                    continue;
                }

                if (is_scalar($value) || $value === null) {
                    $meta[$key] = $value;
                }
            }
        }

        if ($existingOlt !== null) {
            $existing = is_array($existingOlt->meta) ? $existingOlt->meta : [];
            foreach ($hiddenMetaKeys as $key) {
                if (array_key_exists($key, $existing)) {
                    $meta[$key] = $existing[$key];
                }
            }
        }

        if (blank($meta[OltManagementHelper::META_WEB_URL] ?? null)
            && filled($data['management_ip'] ?? null)
            && OltManagementHelper::isAveisDriver($data['olt_driver'] ?? null)) {
            $meta[OltManagementHelper::META_WEB_URL] = OltManagementHelper::defaultAveisWebUrl((string) $data['management_ip']);
        }

        if ($meta !== []) {
            $data['meta'] = $meta;
        }

        $driver = $data['olt_driver'] ?? null;
        if (is_string($driver) && $driver !== '') {
            $vendor = config("olt_drivers.drivers.{$driver}.vendor");
            if (is_string($vendor) && $vendor !== '') {
                $data['vendor'] = $vendor;
            }

            $profile = config("gpon.driver_to_profile.{$driver}");
            if (is_string($profile) && $profile !== '') {
                $data['gpon_profile'] = $profile;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function expandOltFormDataForFill(array $data): array
    {
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        foreach ($meta as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                unset($meta[$key]);
            }
        }
        $data['meta'] = $meta;

        $metaExtra = $meta;
        foreach (OltManagementHelper::metaKeysHiddenFromExtraForm() as $hiddenKey) {
            unset($metaExtra[$hiddenKey]);
        }

        foreach ($metaExtra as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                unset($metaExtra[$key]);
            }
        }

        $data['olt_web_url'] = $meta[OltManagementHelper::META_WEB_URL] ?? null;
        $data['olt_web_username'] = $meta[OltManagementHelper::META_WEB_USERNAME] ?? null;
        $data['olt_vpn_type'] = OltManagementHelper::vpnType(new Device(['meta' => $meta]));
        $data['olt_pptp_server'] = $meta[OltManagementHelper::META_PPTP_SERVER] ?? null;
        $data['olt_pptp_username'] = $meta[OltManagementHelper::META_PPTP_USERNAME] ?? null;
        $data['olt_pptp_subnet'] = $meta[OltManagementHelper::META_PPTP_SUBNET] ?? null;
        $oltId = (int) ($data['id'] ?? 0);
        $ovpnPath = $oltId > 0 ? storage_path('app/private/olt-vpn/'.$oltId.'.ovpn') : null;
        $data['olt_openvpn_config'] = null;
        $data['meta_extra'] = OltManagementHelper::isAveisDriver($data['olt_driver'] ?? null)
            ? []
            : $metaExtra;

        if (is_array($data['olt_health'] ?? null)) {
            $data['olt_health'] = array_filter(
                $data['olt_health'],
                static fn ($value): bool => is_scalar($value) || $value === null,
            );
        }

        $data['is_active'] = ($data['status'] ?? 'active') === 'active';

        return $data;
    }
}
