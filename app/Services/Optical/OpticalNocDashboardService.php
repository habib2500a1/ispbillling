<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\SignalPrediction;
use Illuminate\Support\Facades\Schema;

final class OpticalNocDashboardService
{
    public function __construct(
        private readonly OpticalDashboardService $dashboard,
        private readonly OpticalSignalHistoryService $history,
        private readonly OpticalAiRiskService $ai,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fullSnapshot(int $tenantId): array
    {
        $base = $this->dashboard->snapshot($tenantId);

        $oltCount = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->count();

        $oltsOnline = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', 'active')
            ->count();

        $networkHealth = $this->networkHealthScore($base);

        $aiWarnings = Schema::hasTable('signal_predictions')
            ? $this->ai->refreshTenantPredictions($tenantId, 15)
            : collect();

        return array_merge($base, [
            'olt_total' => $oltCount,
            'olt_online' => $oltsOnline,
            'network_health_score' => $networkHealth,
            'network_health_label' => $this->healthLabel($networkHealth),
            'trend_24h' => $this->history->tenantAverageTrend($tenantId, 24),
            'pon_ports' => $this->history->ponPortStats($tenantId, 20),
            'ai_warnings' => $aiWarnings->map(fn (SignalPrediction $p): array => [
                'id' => $p->id,
                'risk_score' => $p->risk_score,
                'risk_level' => $p->risk_level,
                'type' => $p->prediction_type,
                'summary' => $p->summary,
                'onu_serial' => $p->device?->serial_number,
            ])->values()->all(),
            'critical_list' => $this->dashboard->criticalOnus($tenantId, 10)->map(fn (Device $d): array => [
                'id' => $d->id,
                'serial' => $d->serial_number,
                'rx_dbm' => $d->rx_power_dbm,
                'customer' => $d->customer?->name,
                'olt' => $d->olt?->display_name,
            ])->values()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function networkHealthScore(array $stats): int
    {
        $total = max(1, (int) ($stats['total_onus'] ?? 0));
        $online = (int) ($stats['online_onus'] ?? 0);
        $critical = (int) ($stats['critical_onus'] ?? 0);
        $warning = (int) ($stats['warning_onus'] ?? 0);
        $alerts = (int) ($stats['open_alerts'] ?? 0);
        $avgHealth = (int) ($stats['avg_health'] ?? 70);

        $onlinePct = ($online / $total) * 40;
        $penalty = min(40, ($critical / $total) * 80 + ($warning / $total) * 40 + min(10, $alerts));

        return (int) max(0, min(100, round($avgHealth * 0.5 + $onlinePct + (40 - $penalty))));
    }

    private function healthLabel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Degraded',
            default => 'Critical',
        };
    }
}
