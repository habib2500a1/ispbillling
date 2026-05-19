<?php

namespace App\Support;

use App\Models\Device;

/**
 * Runtime optical / laser power thresholds (config + app_settings overrides).
 */
final class OpticalThresholds
{
    /**
     * @return array<string, float>
     */
    public static function rxBands(): array
    {
        $t = config('optical.rx_thresholds', []);

        return [
            'excellent_max' => (float) ($t['excellent_max'] ?? -8),
            'excellent_min' => (float) ($t['excellent_min'] ?? -15),
            'good_min' => (float) ($t['good_min'] ?? -22),
            'weak_min' => (float) ($t['weak_min'] ?? -27),
        ];
    }

    public static function rxHighWarnAbove(): float
    {
        return (float) config('optical.rx_high_warn_above', -8);
    }

    public static function txNormalMin(): float
    {
        return (float) config('optical.tx_normal_min', 0.5);
    }

    public static function txNormalMax(): float
    {
        return (float) config('optical.tx_normal_max', 5.5);
    }

    public static function txHighWarnAbove(): float
    {
        return (float) config('optical.tx_high_warn_above', 5.5);
    }

    public static function isHighRx(?float $rxDbm): bool
    {
        if ($rxDbm === null) {
            return false;
        }

        return $rxDbm > self::rxHighWarnAbove();
    }

    public static function isHighTx(?float $txDbm): bool
    {
        if ($txDbm === null) {
            return false;
        }

        return $txDbm > self::txHighWarnAbove();
    }

    public static function isLowTx(?float $txDbm): bool
    {
        if ($txDbm === null) {
            return false;
        }

        return $txDbm < self::txNormalMin();
    }

    /**
     * Human-readable band summary for admin UI.
     */
    public static function bandSummaryText(): string
    {
        $b = self::rxBands();
        $rxHigh = self::rxHighWarnAbove();

        return sprintf(
            'Laser high if RX > %s dBm · Excellent %s to %s · Good %s to %s · Weak %s to %s · Critical < %s · TX normal %s–%s dBm · TX high > %s',
            number_format($rxHigh, 1),
            number_format($b['excellent_max'], 1),
            number_format($b['excellent_min'], 1),
            number_format($b['excellent_min'], 1),
            number_format($b['good_min'], 1),
            number_format($b['good_min'], 1),
            number_format($b['weak_min'], 1),
            number_format($b['weak_min'], 1),
            number_format(self::txNormalMin(), 1),
            number_format(self::txNormalMax(), 1),
            number_format(self::txHighWarnAbove(), 1),
        );
    }

    /**
     * @return array{
     *   rx: ?float,
     *   tx: ?float,
     *   rx_level: string,
     *   tx_level: string,
     *   rx_label: string,
     *   tx_label: string,
     *   is_high_rx: bool,
     *   is_high_tx: bool,
     *   fix_hint: ?string,
     *   oper_status: ?string,
     * }
     */
    public static function laserStatusForOnu(?Device $onu): array
    {
        if ($onu === null || $onu->type !== 'onu') {
            return [
                'rx' => null,
                'tx' => null,
                'rx_level' => OnuSignalLevel::UNKNOWN,
                'tx_level' => OnuSignalLevel::UNKNOWN,
                'rx_label' => '—',
                'tx_label' => '—',
                'is_high_rx' => false,
                'is_high_tx' => false,
                'fix_hint' => null,
                'oper_status' => null,
            ];
        }

        $oper = strtolower((string) ($onu->onu_oper_status ?? ''));
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $tx = $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null;
        $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);
        $txLevel = OnuSignalLevel::classifyTx($tx);
        $highRx = self::isHighRx($rx);
        $highTx = self::isHighTx($tx);

        $fixHint = null;
        if ($highRx && $highTx) {
            $fixHint = 'RX ও TX দুটোই উচ্চ — attenuator/প্যাচ কর্ড চেক করুন অথবা Laser thresholds সেটিংসে সীমা ঠিক করুন।';
        } elseif ($highRx) {
            $fixHint = 'RX laser উচ্চ (>' . number_format(self::rxHighWarnAbove(), 1) . ' dBm) — ছোট ফাইবার, reflectance বা OLT calibration। Attenuator বা threshold সামঞ্জস্য করুন।';
        } elseif ($highTx) {
            $fixHint = 'ONU TX laser উচ্চ — ONU laser fault বা ভুল calibration। Threshold বা ONU replace বিবেচনা করুন।';
        } elseif ($rxLevel === OnuSignalLevel::CRITICAL || $rxLevel === OnuSignalLevel::WARNING) {
            $fixHint = 'দুর্বল RX — কানেক্টর, splice, splitter চেক করুন।';
        }

        return [
            'rx' => $rx,
            'tx' => $tx,
            'rx_level' => $rxLevel,
            'tx_level' => $txLevel,
            'rx_label' => OnuSignalLevel::labels()[$rxLevel] ?? $rxLevel,
            'tx_label' => OnuSignalLevel::labels()[$txLevel] ?? $txLevel,
            'is_high_rx' => $highRx,
            'is_high_tx' => $highTx,
            'fix_hint' => $fixHint,
            'oper_status' => $onu->onu_oper_status,
        ];
    }
}
