<?php

namespace App\Filament\Resources\OltResource\Concerns;

use App\Support\OltManagementHelper;

trait NormalizesOltFormData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeOltFormData(array $data): array
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
        $webPassword = $data['olt_web_password'] ?? null;
        unset($data['olt_web_password']);

        $meta = OltManagementHelper::mergeWebCredentialsIntoMeta(
            $meta,
            isset($data['olt_web_url']) ? (string) $data['olt_web_url'] : null,
            isset($data['olt_web_username']) ? (string) $data['olt_web_username'] : null,
            is_string($webPassword) ? $webPassword : null,
        );
        unset($data['olt_web_url'], $data['olt_web_username']);

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

        $data['olt_web_url'] = $meta[OltManagementHelper::META_WEB_URL] ?? null;
        $data['olt_web_username'] = $meta[OltManagementHelper::META_WEB_USERNAME] ?? null;
        $data['is_active'] = ($data['status'] ?? 'active') === 'active';

        return $data;
    }
}
