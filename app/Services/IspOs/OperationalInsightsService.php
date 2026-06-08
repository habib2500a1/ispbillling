<?php

namespace App\Services\IspOs;

use App\Models\Device;
use App\Models\PonSignalStat;
use App\Models\SignalPrediction;
use App\Models\SupportTicket;
use App\Support\TenantResolver;

final class OperationalInsightsService
{
    /**
     * @return list<array{message: string, tone: string}>
     */
    public function forTenant(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $insights = array_merge(
            $this->ponCapacityInsights($tenantId),
            $this->signalInsights($tenantId),
            $this->oltTemperatureInsights($tenantId),
            $this->complaintZoneInsights($tenantId),
        );

        return array_slice($insights, 0, 8);
    }

    /**
     * @return list<array{message: string, tone: string}>
     */
    private function ponCapacityInsights(int $tenantId): array
    {
        $out = [];
        $stats = PonSignalStat::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with('oltPort.device:id,display_name,label')
            ->orderByDesc('onu_total')
            ->limit(3)
            ->get();

        foreach ($stats as $pon) {
            $total = (int) ($pon->onu_total ?? 0);
            if ($total < 20) {
                continue;
            }
            $capacity = (int) config('optical.pon_max_onus', 64);
            $pct = $capacity > 0 ? round(100 * $total / $capacity) : 0;
            if ($pct >= 75) {
                $oltName = $pon->oltPort?->device?->display_name ?? $pon->oltPort?->device?->label ?? 'OLT';
                $out[] = [
                    'message' => "PON utilization high — {$oltName} port at {$pct}% capacity ({$total} ONUs).",
                    'tone' => $pct >= 90 ? 'critical' : 'warning',
                ];
            }
        }

        return $out;
    }

    /**
     * @return list<array{message: string, tone: string}>
     */
    private function signalInsights(int $tenantId): array
    {
        return SignalPrediction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('risk_level', ['warning', 'critical', 'emergency'])
            ->orderByDesc('risk_score')
            ->limit(3)
            ->get()
            ->map(fn (SignalPrediction $p): array => [
                'message' => $p->summary ?: 'ONU signal degrading — review optical path.',
                'tone' => in_array($p->risk_level, ['critical', 'emergency'], true) ? 'critical' : 'warning',
            ])
            ->all();
    }

    /**
     * @return list<array{message: string, tone: string}>
     */
    private function oltTemperatureInsights(int $tenantId): array
    {
        $out = [];
        $olts = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->whereNotNull('olt_health')
            ->limit(20)
            ->get(['id', 'display_name', 'label', 'olt_health']);

        foreach ($olts as $olt) {
            $temp = $olt->olt_health['temperature_c'] ?? null;
            if ($temp !== null && (float) $temp >= 60) {
                $name = $olt->display_name ?? $olt->label ?? 'OLT';
                $out[] = [
                    'message' => "OLT temperature warning — {$name} at {$temp} °C (above normal).",
                    'tone' => (float) $temp >= 70 ? 'critical' : 'warning',
                ];
            }
        }

        return array_slice($out, 0, 2);
    }

    /**
     * @return list<array{message: string, tone: string}>
     */
    private function complaintZoneInsights(int $tenantId): array
    {
        $since = now()->subDays(7);
        $rows = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->whereHas('customer.zone')
            ->with('customer.zone:id,name')
            ->get()
            ->groupBy(fn (SupportTicket $t) => $t->customer?->zone?->name ?? 'Unknown')
            ->map->count()
            ->sortDesc()
            ->take(1);

        $out = [];
        foreach ($rows as $zone => $count) {
            if ($count >= 5) {
                $out[] = [
                    'message' => "Area generating excessive complaints — {$zone} ({$count} tickets in 7 days).",
                    'tone' => 'warning',
                ];
            }
        }

        return $out;
    }
}
