<?php

namespace App\Support;

use App\Models\Device;
use App\Models\OnuSignalLog;

/**
 * Resolve ONU temperature (°C) and supply voltage (V) from device meta / optical sub-array.
 */
final class OnuEnvironmentalMetrics
{
    /**
     * @return array{temperature_c: ?float, voltage_v: ?float}
     */
    public static function fromDevice(Device $onu): array
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $optical = is_array($meta['optical'] ?? null) ? $meta['optical'] : [];
        $bag = array_merge($optical, $meta);

        $temperatureC = self::parseTemperature($bag);
        $voltageV = self::parseVoltage($bag);

        if ($temperatureC === null || $voltageV === null) {
            $log = OnuSignalLog::query()
                ->where('device_id', $onu->id)
                ->orderByDesc('sampled_at')
                ->first(['temperature_c', 'voltage_v']);

            if ($log !== null) {
                $temperatureC ??= $log->temperature_c !== null ? (float) $log->temperature_c : null;
                $voltageV ??= $log->voltage_v !== null ? (float) $log->voltage_v : null;
            }
        }

        return [
            'temperature_c' => $temperatureC,
            'voltage_v' => $voltageV,
        ];
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    public static function parseTemperature(array $bag): ?float
    {
        return self::firstNumeric($bag, config('gpon.onu_meta_keys.temperature_c', [
            'temperature_c', 'temperature', 'onu_temperature', 'temp_c', 'temp',
        ]));
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    public static function parseVoltage(array $bag): ?float
    {
        return self::firstNumeric($bag, config('gpon.onu_meta_keys.voltage_v', [
            'voltage_v', 'voltage', 'onu_voltage', 'supply_voltage',
        ]));
    }

    public static function formatTemperature(?float $c): string
    {
        if ($c === null) {
            return '—';
        }

        return number_format($c, 0).' °C';
    }

    public static function formatVoltage(?float $v): string
    {
        if ($v === null) {
            return '—';
        }

        return number_format($v, 2).' V';
    }

    public static function temperatureTone(?float $c): string
    {
        if ($c === null) {
            return 'gray';
        }

        $warn = (float) config('optical.onu_temperature_warning_c', 65);
        $crit = (float) config('optical.onu_temperature_critical_c', 75);

        return match (true) {
            $c >= $crit => 'danger',
            $c >= $warn => 'warning',
            default => 'success',
        };
    }

    /**
     * @param  array<string, mixed>  $bag
     * @param  list<string>  $keys
     */
    private static function firstNumeric(array $bag, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $bag) || $bag[$key] === '' || $bag[$key] === null) {
                continue;
            }

            if (! is_numeric($bag[$key])) {
                continue;
            }

            $value = (float) $bag[$key];

            if ($key === 'temperature' || $key === 'temp' || str_contains($key, 'temperature')) {
                if ($value > 200) {
                    $value = $value / 10;
                }
                if ($value < 5 || $value > 120) {
                    continue;
                }
            }

            if (str_contains($key, 'voltage')) {
                if ($value < 2.0 || $value > 6.5) {
                    continue;
                }
            }

            return round($value, $key === 'voltage_v' || str_contains($key, 'voltage') ? 3 : 2);
        }

        return null;
    }
}
