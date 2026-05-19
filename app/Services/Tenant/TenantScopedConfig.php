<?php

namespace App\Services\Tenant;

use App\Models\AppSetting;

final class TenantScopedConfig
{
    public static function apply(?int $tenantId): void
    {
        if ($tenantId === null || $tenantId < 1) {
            return;
        }

        $prefix = "tenant.{$tenantId}.";

        foreach (AppSetting::query()->where('key', 'like', $prefix.'%')->cursor() as $row) {
            $configKey = substr($row->key, strlen($prefix));
            if ($configKey === '') {
                continue;
            }
            try {
                $raw = $row->value;
                if ($raw === null || $raw === '') {
                    continue;
                }
                config([$configKey => AppSetting::castValueForConfigKey($configKey, $raw)]);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    public static function put(int $tenantId, string $configKey, string $value): void
    {
        AppSetting::putValue("tenant.{$tenantId}.{$configKey}", $value);
    }
}
