<?php

namespace App\Services\Collector;

use App\Models\CollectorVisit;
use App\Support\TenantResolver;
use Carbon\Carbon;

final class CollectorVisitsReportService
{
    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   visit_count: int,
     *   total_collected: float,
     *   with_gps: int,
     *   map_points: list<array{lat: float, lng: float, label: string, amount: float|null}>,
     *   visits: \Illuminate\Support\Collection,
     *   leaderboard: list<array{collector: string, visits: int, total: float}>
     * }
     */
    public function report(?Carbon $from = null, ?Carbon $to = null, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $from = ($from ?? now())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $visits = CollectorVisit::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('visited_at', [$from, $to])
            ->with(['collector:id,name', 'customer:id,name,customer_code'])
            ->orderByDesc('visited_at')
            ->get();

        $mapPoints = [];
        $leaderboard = [];

        foreach ($visits as $visit) {
            $collectorName = $visit->collector?->name ?? 'Unknown';
            $leaderboard[$collectorName] ??= ['collector' => $collectorName, 'visits' => 0, 'total' => 0.0];
            $leaderboard[$collectorName]['visits']++;
            $leaderboard[$collectorName]['total'] += (float) ($visit->amount_collected ?? 0);

            if ($visit->latitude !== null && $visit->longitude !== null) {
                $mapPoints[] = [
                    'lat' => (float) $visit->latitude,
                    'lng' => (float) $visit->longitude,
                    'label' => ($visit->customer?->name ?? 'Visit').' · '.number_format((float) ($visit->amount_collected ?? 0), 0).' BDT',
                    'amount' => $visit->amount_collected !== null ? (float) $visit->amount_collected : null,
                ];
            }
        }

        uasort($leaderboard, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'visit_count' => $visits->count(),
            'total_collected' => round((float) $visits->sum('amount_collected'), 2),
            'with_gps' => $visits->filter(fn ($v) => $v->latitude !== null)->count(),
            'map_points' => $mapPoints,
            'visits' => $visits,
            'leaderboard' => array_values($leaderboard),
        ];
    }
}
