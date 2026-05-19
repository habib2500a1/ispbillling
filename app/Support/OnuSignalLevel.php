<?php

namespace App\Support;

final class OnuSignalLevel
{
    public const EXCELLENT = 'excellent';

    public const GOOD = 'good';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const OFFLINE = 'offline';

    public const UNKNOWN = 'unknown';

    /** RX laser too strong (short fibre, reflectance, calibration). */
    public const HIGH = 'high';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::EXCELLENT => 'Excellent',
            self::GOOD => 'Good',
            self::WARNING => 'Weak',
            self::CRITICAL => 'Critical',
            self::HIGH => 'Laser high',
            self::OFFLINE => 'Offline',
            self::UNKNOWN => 'Unknown',
        ];
    }

    public static function classifyRx(?float $rxDbm, ?string $operStatus = null): string
    {
        if (in_array($operStatus, ['offline', 'los', 'power_fail'], true)) {
            return self::OFFLINE;
        }

        if ($rxDbm === null) {
            return self::UNKNOWN;
        }

        if (OpticalThresholds::isHighRx($rxDbm)) {
            return self::HIGH;
        }

        $t = OpticalThresholds::rxBands();
        $excellentMax = $t['excellent_max'];
        $excellentMin = $t['excellent_min'];
        $goodMin = $t['good_min'];
        $weakMin = $t['weak_min'];

        if ($rxDbm <= $excellentMax && $rxDbm > $excellentMin) {
            return self::EXCELLENT;
        }
        if ($rxDbm <= $excellentMin && $rxDbm > $goodMin) {
            return self::GOOD;
        }
        if ($rxDbm <= $goodMin && $rxDbm > $weakMin) {
            return self::WARNING;
        }

        return self::CRITICAL;
    }

    public static function classifyTx(?float $txDbm): string
    {
        if ($txDbm === null) {
            return self::UNKNOWN;
        }

        if (OpticalThresholds::isHighTx($txDbm)) {
            return self::HIGH;
        }

        $min = OpticalThresholds::txNormalMin();
        $max = OpticalThresholds::txNormalMax();

        if ($txDbm >= $min && $txDbm <= $max) {
            return self::GOOD;
        }

        return self::WARNING;
    }

    public static function filamentColor(string $level): string
    {
        return (string) (config('optical.colors')[$level] ?? 'gray');
    }

    public static function healthScoreFromRxLevel(string $rxLevel): int
    {
        return match ($rxLevel) {
            self::EXCELLENT => 98,
            self::GOOD => 85,
            self::WARNING => 65,
            self::CRITICAL => 35,
            self::HIGH => 45,
            self::OFFLINE => 10,
            default => 50,
        };
    }
}
