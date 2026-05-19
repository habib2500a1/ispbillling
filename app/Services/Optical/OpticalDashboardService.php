<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\FiberFaultLog;
use App\Models\OnuHealthScore;
use App\Models\OnuSignalLog;
use App\Models\SignalAlert;
use App\Support\OnuSignalLevel;
use Illuminate\Support\Collection;

final class OpticalDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $tenantId): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('onu_signal_logs')) {
            return $this->emptySnapshot();
        }

        $onus = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->get();

        $summary = FiberFaultDetector::summarizeOnus($onus);

        $rxValues = $onus->map(fn (Device $o) => $o->rx_power_dbm !== null ? (float) $o->rx_power_dbm : null)->filter();

        $openAlerts = SignalAlert::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->count();

        $fiberFaults = FiberFaultLog::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->count();

        $avgHealth = (int) OnuHealthScore::query()
            ->where('tenant_id', $tenantId)
            ->avg('health_score');

        return [
            'total_onus' => $summary['total'],
            'online_onus' => $summary['total'] - $summary['offline'],
            'critical_onus' => $summary['critical'],
            'warning_onus' => $summary['warning'],
            'offline_onus' => $summary['offline'],
            'excellent_onus' => $summary['excellent'],
            'avg_rx_dbm' => $rxValues->isNotEmpty() ? round($rxValues->avg(), 2) : null,
            'open_alerts' => $openAlerts,
            'fiber_faults' => $fiberFaults,
            'avg_health' => $avgHealth,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySnapshot(): array
    {
        return [
            'total_onus' => 0,
            'online_onus' => 0,
            'critical_onus' => 0,
            'warning_onus' => 0,
            'offline_onus' => 0,
            'excellent_onus' => 0,
            'avg_rx_dbm' => null,
            'open_alerts' => 0,
            'fiber_faults' => 0,
            'avg_health' => 0,
        ];
    }

    /**
     * @return Collection<int, Device>
     */
    public function criticalOnus(int $tenantId, int $limit = 20): Collection
    {
        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('rx_power_dbm')
            ->orderBy('rx_power_dbm')
            ->limit($limit)
            ->with(['customer', 'olt'])
            ->get()
            ->filter(function (Device $onu): bool {
                $level = OnuSignalLevel::classifyRx(
                    $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null,
                    strtolower((string) ($onu->onu_oper_status ?? '')),
                );

                return in_array($level, [OnuSignalLevel::CRITICAL, OnuSignalLevel::WARNING], true);
            });
    }

    /**
     * @return array{labels: list<string>, rx: list<float|null>, tx: list<float|null>}
     */
    public function signalTrend(int $deviceId, int $hours = 48): array
    {
        $since = now()->subHours($hours);
        $logs = OnuSignalLog::query()
            ->where('device_id', $deviceId)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rx_power_dbm', 'tx_power_dbm', 'granularity']);

        $labels = [];
        $rx = [];
        $tx = [];

        foreach ($logs as $log) {
            if ($log->granularity === 'snapshot' && count($labels) > 200) {
                continue;
            }
            $labels[] = $log->sampled_at->format('M j H:i');
            $rx[] = $log->rx_power_dbm !== null ? (float) $log->rx_power_dbm : null;
            $tx[] = $log->tx_power_dbm !== null ? (float) $log->tx_power_dbm : null;
        }

        return ['labels' => $labels, 'rx' => $rx, 'tx' => $tx];
    }
}
