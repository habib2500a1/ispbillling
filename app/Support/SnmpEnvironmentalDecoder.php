<?php

namespace App\Support;

/**
 * Normalize vendor SNMP integers to °C / V for ONU telemetry.
 */
final class SnmpEnvironmentalDecoder
{
    public static function temperatureC(?int $raw, string $vendor = 'generic'): ?float
    {
        if ($raw === null || $raw === 0) {
            return null;
        }

        $v = strtolower($vendor);

        if (str_contains($v, 'bdcom')) {
            // NMS-EPON-ONU opModuleTemp — unit 1/256 °C
            return round($raw / 256, 2);
        }

        if (str_contains($v, 'vsol') || str_contains($v, 'ecom') || str_contains($v, 'cdata')) {
            return round(abs($raw) > 200 ? $raw / 10 : (float) $raw, 2);
        }

        return round((float) $raw, 2);
    }

    public static function voltageV(?int $raw, string $vendor = 'generic'): ?float
    {
        if ($raw === null || $raw === 0) {
            return null;
        }

        $v = strtolower($vendor);

        if (str_contains($v, 'aveis')) {
            $mode = (string) config('gpon.aveis_voltage_mode', 'hundredth_v');

            return match ($mode) {
                'tenth_v' => round($raw / 10, 3),
                'raw' => round((float) $raw, 3),
                default => round($raw / 100, 3),
            };
        }

        if (str_contains($v, 'bdcom')) {
            // NMS-EPON-ONU opModuleVolt — unit 100 µV
            return round($raw / 10000, 3);
        }

        if (str_contains($v, 'vsol') || str_contains($v, 'ecom') || str_contains($v, 'cdata')) {
            return round($raw > 50 ? $raw / 100 : (float) $raw, 3);
        }

        return round($raw > 50 ? $raw / 100 : (float) $raw, 3);
    }
}
