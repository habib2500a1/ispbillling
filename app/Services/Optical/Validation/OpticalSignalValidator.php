<?php

namespace App\Services\Optical\Validation;

use App\Models\OnuSignalLog;

/**
 * Spike rejection + rolling average over recent OLT readings.
 */
final class OpticalSignalValidator
{
    /**
     * @return array{
     *   rx_dbm: ?float,
     *   tx_dbm: ?float,
     *   is_spike: bool,
     *   sample_count: int,
     *   rx_stddev: ?float
     * }
     */
    public function smooth(
        int $deviceId,
        ?float $rawRx,
        ?float $rawTx,
        int $windowSize = 0,
    ): array {
        $window = $windowSize > 0
            ? $windowSize
            : (int) config('optical.smoothing.window_size', 5);

        $history = OnuSignalLog::query()
            ->where('device_id', $deviceId)
            ->where('granularity', 'snapshot')
            ->where('is_spike', false)
            ->orderByDesc('sampled_at')
            ->limit(max(1, $window - 1))
            ->get(['rx_power_dbm', 'tx_power_dbm']);

        $rxSamples = [];
        $txSamples = [];

        foreach ($history as $row) {
            if ($row->rx_power_dbm !== null) {
                $rxSamples[] = (float) $row->rx_power_dbm;
            }
            if ($row->tx_power_dbm !== null) {
                $txSamples[] = (float) $row->tx_power_dbm;
            }
        }

        if ($rawRx !== null && ! $this->isSpike($rawRx, $rxSamples)) {
            $rxSamples[] = $rawRx;
        }

        if ($rawTx !== null && ! $this->isSpike($rawTx, $txSamples)) {
            $txSamples[] = $rawTx;
        }

        $isSpike = ($rawRx !== null && $this->isSpike($rawRx, array_slice($rxSamples, 0, -1)))
            || ($rawTx !== null && $this->isSpike($rawTx, array_slice($txSamples, 0, -1)));

        return [
            'rx_dbm' => $this->rollingAverage($rxSamples),
            'tx_dbm' => $this->rollingAverage($txSamples),
            'is_spike' => $isSpike,
            'sample_count' => count($rxSamples),
            'rx_stddev' => $this->standardDeviation($rxSamples),
        ];
    }

    /**
     * @param  list<float>  $samples
     */
    public function isSpike(float $reading, array $samples): bool
    {
        if ($samples === []) {
            return false;
        }

        $threshold = (float) config('optical.smoothing.spike_threshold_db', 5.0);
        $median = $this->median($samples);

        return abs($reading - $median) > $threshold;
    }

    /**
     * @param  list<float>  $samples
     */
    private function rollingAverage(array $samples): ?float
    {
        if ($samples === []) {
            return null;
        }

        $minSamples = (int) config('optical.smoothing.min_samples', 1);
        if (count($samples) < $minSamples) {
            return round(array_sum($samples) / count($samples), 3);
        }

        $trim = (int) config('optical.smoothing.trim_extremes', 0);
        if ($trim > 0 && count($samples) > ($trim * 2 + 1)) {
            sort($samples);
            $samples = array_slice($samples, $trim, -$trim);
        }

        return round(array_sum($samples) / count($samples), 3);
    }

    /**
     * @param  list<float>  $samples
     */
    private function median(array $samples): float
    {
        sort($samples);
        $c = count($samples);
        $mid = intdiv($c, 2);

        return $c % 2 === 0
            ? ($samples[$mid - 1] + $samples[$mid]) / 2
            : $samples[$mid];
    }

    /**
     * @param  list<float>  $samples
     */
    private function standardDeviation(array $samples): ?float
    {
        $c = count($samples);
        if ($c < 2) {
            return null;
        }

        $mean = array_sum($samples) / $c;
        $variance = 0.0;
        foreach ($samples as $v) {
            $variance += ($v - $mean) ** 2;
        }

        return round(sqrt($variance / $c), 3);
    }
}
