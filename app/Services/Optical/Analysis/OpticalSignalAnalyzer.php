<?php

namespace App\Services\Optical\Analysis;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Support\OnuSignalLevel;

final class OpticalSignalAnalyzer
{
    /**
     * @return array{
     *   stability_score: int,
     *   fiber_health_score: int,
     *   rx_level: string,
     *   rx_trend_dbm: ?float,
     *   root_cause_hint: ?string,
     *   neighbor_delta_db: ?float
     * }
     */
    public function analyze(
        Device $onu,
        ?float $smoothedRx,
        ?float $smoothedTx,
        ?float $rxStddev,
        ?string $operStatus = null,
    ): array {
        $oper = strtolower((string) ($operStatus ?? $onu->onu_oper_status ?? 'unknown'));
        $rxLevel = OnuSignalLevel::classifyRx($smoothedRx, $oper);

        $stability = $this->stabilityScore($rxStddev, $onu->id);
        $fiberHealth = (int) round(($stability * 0.6) + (OnuSignalLevel::healthScoreFromRxLevel($rxLevel) * 0.4));

        $trend = $this->trendDbm($onu->id, $smoothedRx);
        $neighborDelta = $this->neighborDelta($onu, $smoothedRx);
        $hint = $this->rootCause($onu, $rxLevel, $oper, $trend, $rxStddev, $neighborDelta);

        return [
            'stability_score' => $stability,
            'fiber_health_score' => min(100, max(0, $fiberHealth)),
            'rx_level' => $rxLevel,
            'rx_trend_dbm' => $trend,
            'root_cause_hint' => $hint,
            'neighbor_delta_db' => $neighborDelta,
        ];
    }

    private function stabilityScore(?float $stddev, int $deviceId): int
    {
        if ($stddev === null) {
            $recent = OnuSignalLog::query()
                ->where('device_id', $deviceId)
                ->where('granularity', 'snapshot')
                ->where('is_spike', false)
                ->orderByDesc('sampled_at')
                ->limit(5)
                ->pluck('rx_power_dbm')
                ->filter()
                ->map(fn ($v) => (float) $v)
                ->all();

            if (count($recent) < 2) {
                return 70;
            }

            $maxDelta = 0.0;
            for ($i = 1; $i < count($recent); $i++) {
                $maxDelta = max($maxDelta, abs($recent[$i] - $recent[$i - 1]));
            }

            return (int) max(20, min(100, round(100 - $maxDelta * 8)));
        }

        return (int) max(15, min(100, round(100 - $stddev * 12)));
    }

    private function trendDbm(int $deviceId, ?float $currentRx): ?float
    {
        if ($currentRx === null) {
            return null;
        }

        $prev = OnuSignalLog::query()
            ->where('device_id', $deviceId)
            ->where('granularity', 'snapshot')
            ->where('is_spike', false)
            ->orderByDesc('sampled_at')
            ->skip(1)
            ->value('rx_power_dbm');

        if ($prev === null) {
            return null;
        }

        return round($currentRx - (float) $prev, 3);
    }

    private function neighborDelta(Device $onu, ?float $smoothedRx): ?float
    {
        if ($smoothedRx === null || $onu->olt_id === null) {
            return null;
        }

        $ponAvg = Device::query()
            ->withoutGlobalScopes()
            ->where('type', 'onu')
            ->where('olt_id', $onu->olt_id)
            ->when($onu->pon_no !== null, fn ($q) => $q->where('pon_no', $onu->pon_no))
            ->whereNotNull('rx_power_dbm')
            ->where('id', '!=', $onu->id)
            ->avg('rx_power_dbm');

        if ($ponAvg === null) {
            return null;
        }

        return round($smoothedRx - (float) $ponAvg, 3);
    }

    private function rootCause(
        Device $onu,
        string $rxLevel,
        string $oper,
        ?float $trend,
        ?float $stddev,
        ?float $neighborDelta,
    ): ?string {
        $rules = config('optical.root_cause_rules', []);

        if (in_array($oper, ['los', 'offline'], true)) {
            return $rules['los'] ?? 'fiber_break';
        }
        if ($oper === 'power_fail') {
            return $rules['power_fail'] ?? 'onu_power';
        }

        if ($trend !== null && $trend <= -((float) config('optical.sudden_drop_db', 3))) {
            return 'sudden_attenuation';
        }

        if ($stddev !== null && $stddev > (float) config('optical.smoothing.unstable_stddev_db', 2.5)) {
            return 'unstable_signal';
        }

        if ($neighborDelta !== null && $neighborDelta < -4 && $rxLevel !== OnuSignalLevel::EXCELLENT) {
            return $rules['weak_rx_cluster'] ?? 'patch_cord_or_splitter';
        }

        if ($rxLevel === OnuSignalLevel::CRITICAL) {
            return $rules['weak_rx_cluster'] ?? 'long_fiber_or_dirty_connector';
        }

        return null;
    }
}
