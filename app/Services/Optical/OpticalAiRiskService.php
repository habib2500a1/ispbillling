<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OnuHealthScore;
use App\Models\SignalAlert;
use App\Models\SignalPrediction;
use App\Support\OnuSignalLevel;
use App\Support\OpticalThresholds;
use Illuminate\Support\Collection;

/**
 * Heuristic "AI" risk engine — trend + stability + alerts (no external ML).
 */
final class OpticalAiRiskService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function analyzeOnu(Device $onu): array
    {
        $predictions = [];
        $health = $onu->onuHealthScore;
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $oper = strtolower((string) ($onu->onu_oper_status ?? ''));
        $level = OnuSignalLevel::classifyRx($rx, $oper);

        $risk = 10;
        $factors = [];

        if ($level === OnuSignalLevel::CRITICAL || $level === OnuSignalLevel::OFFLINE) {
            $risk += 50;
            $factors[] = 'Critical RX or LOS/offline status';
        } elseif ($level === OnuSignalLevel::WARNING) {
            $risk += 30;
            $factors[] = 'Weak RX power';
        }

        if ($health !== null) {
            if ((int) $health->stability_score < 50) {
                $risk += 25;
                $factors[] = 'Unstable signal (high RX variance)';
            }
            if ((int) $health->fiber_health_score < 60) {
                $risk += 20;
                $factors[] = 'Low fiber health score';
            }
            if ($health->rx_trend_dbm !== null && (float) $health->rx_trend_dbm < -2) {
                $risk += 25;
                $factors[] = 'RX degrading '.number_format((float) $health->rx_trend_dbm, 1).' dB trend';
            }
        }

        $openAlerts = SignalAlert::query()
            ->where('device_id', $onu->id)
            ->where('status', 'open')
            ->count();
        if ($openAlerts > 0) {
            $risk += min(20, $openAlerts * 10);
            $factors[] = "{$openAlerts} open optical alert(s)";
        }

        $risk = min(100, $risk);
        $riskLevel = $this->riskLevel($risk);

        if ($risk >= 40) {
            $type = match (true) {
                $level === OnuSignalLevel::OFFLINE => 'fiber_cut_risk',
                $health?->rx_trend_dbm !== null && (float) $health->rx_trend_dbm < -3 => 'signal_degradation',
                default => 'maintenance_recommended',
            };

            $predictions[] = $this->storePrediction($onu, $type, $risk, $riskLevel, $factors);
        }

        return $predictions;
    }

    /**
     * Run for tenant and return top risks.
     *
     * @return Collection<int, SignalPrediction>
     */
    public function refreshTenantPredictions(int $tenantId, int $limit = 30): Collection
    {
        SignalPrediction::query()
            ->where('tenant_id', $tenantId)
            ->where('expires_at', '<', now())
            ->delete();

        $onus = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->with('onuHealthScore')
            ->where(function ($q): void {
                $critical = (float) config('optical.rx_thresholds.critical', -27);
                $warn = (float) config('optical.rx_thresholds.warning', -25);
                $q->where('rx_power_dbm', '<', $warn)
                    ->orWhereIn('onu_oper_status', ['offline', 'los', 'power_fail'])
                    ->orWhereHas('onuHealthScore', fn ($h) => $h->where('stability_score', '<', 55));
            })
            ->limit(200)
            ->get();

        foreach ($onus as $onu) {
            $this->analyzeOnu($onu);
        }

        return SignalPrediction::query()
            ->where('tenant_id', $tenantId)
            ->where('predicted_at', '>=', now()->subHours(6))
            ->orderByDesc('risk_score')
            ->limit($limit)
            ->with('device:id,serial_number,customer_id')
            ->get();
    }

    /**
     * @param  list<string>  $factors
     * @return array<string, mixed>
     */
    private function storePrediction(Device $onu, string $type, int $risk, string $level, array $factors): array
    {
        $summary = match ($type) {
            'fiber_cut_risk' => 'High probability of fiber cut or ONU power loss — dispatch field check.',
            'signal_degradation' => 'RX power trending down — inspect connectors and splitter path.',
            default => 'Schedule preventive maintenance before subscriber complains.',
        };

        $record = SignalPrediction::query()->updateOrCreate(
            [
                'tenant_id' => $onu->tenant_id,
                'device_id' => $onu->id,
                'prediction_type' => $type,
            ],
            [
                'olt_id' => $onu->olt_id,
                'scope' => 'onu',
                'risk_score' => $risk,
                'risk_level' => $level,
                'summary' => $summary,
                'factors' => $factors,
                'predicted_at' => now(),
                'expires_at' => now()->addHours(12),
            ],
        );

        return $record->toArray();
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score >= 85 => SignalPrediction::LEVEL_EMERGENCY,
            $score >= 65 => SignalPrediction::LEVEL_CRITICAL,
            $score >= 40 => SignalPrediction::LEVEL_WARNING,
            default => SignalPrediction::LEVEL_NORMAL,
        };
    }
}
