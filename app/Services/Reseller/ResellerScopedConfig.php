<?php

namespace App\Services\Reseller;

use App\Models\AppSetting;

final class ResellerScopedConfig
{
    public static function prefix(int $resellerId): string
    {
        return "reseller.{$resellerId}.";
    }

    public static function storageKey(int $resellerId, string $configKey): string
    {
        return self::prefix($resellerId).$configKey;
    }

    public static function apply(?int $resellerId): void
    {
        if ($resellerId === null || $resellerId < 1) {
            return;
        }

        $prefix = self::prefix($resellerId);

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

    public static function put(int $resellerId, string $configKey, string $value): void
    {
        AppSetting::putValue(self::storageKey($resellerId, $configKey), $value);
    }

    public static function get(int $resellerId, string $configKey): ?string
    {
        return AppSetting::getStoredValue(self::storageKey($resellerId, $configKey));
    }

    /**
     * @param  callable(): mixed  $callback
     */
    public static function using(int $resellerId, callable $callback): mixed
    {
        $snapshot = config()->all();
        self::apply($resellerId);

        try {
            return $callback();
        } finally {
            config()->set($snapshot);
        }
    }
}
