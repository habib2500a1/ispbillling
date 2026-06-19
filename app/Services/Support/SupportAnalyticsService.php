<?php

namespace App\Services\Support;

use App\Models\Area;
use App\Models\Device;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SupportCategories;
use Illuminate\Support\Facades\DB;

final class SupportAnalyticsService
{
    /**
     * @return list<array{label: string, value: string, hint: string}>
     */
    public function mttrStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $resolved = SupportTicket::query()
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at', 'first_responded_at']);

        $mttrHours = 0.0;
        $mtfrHours = 0.0;
        $mttrCount = $resolved->count();
        $mtfrCount = $resolved->whereNotNull('first_responded_at')->count();

        if ($mttrCount > 0) {
            $mttrHours = round(
                $resolved->avg(fn (SupportTicket $t): float => (float) $t->created_at?->diffInMinutes($t->resolved_at) / 60),
                1,
            );
        }

        if ($mtfrCount > 0) {
            $mtfrHours = round(
                $resolved->whereNotNull('first_responded_at')->avg(
                    fn (SupportTicket $t): float => (float) $t->created_at?->diffInMinutes($t->first_responded_at) / 60,
                ),
                1,
            );
        }

        return [
            ['label' => 'MTTR ('.$days.'d)', 'value' => $mttrHours.'h', 'hint' => 'Mean time to resolve'],
            ['label' => 'MTFR ('.$days.'d)', 'value' => $mtfrHours.'h', 'hint' => 'Mean time to first response'],
            ['label' => 'Resolved ('.$days.'d)', 'value' => number_format($mttrCount), 'hint' => 'Tickets closed'],
        ];
    }

    /**
     * @return array{avg: float, count: int, rate: float}
     */
    public function csatSummary(int $days = 30): array
    {
        $since = now()->subDays($days);
        $rated = SupportTicket::query()
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('customer_rating')
            ->get(['customer_rating']);

        $count = $rated->count();
        $avg = $count > 0 ? round($rated->avg('customer_rating'), 1) : 0.0;

        $resolved = SupportTicket::query()
            ->where('resolved_at', '>=', $since)
            ->count();

        $rate = $resolved > 0 ? round(($count / $resolved) * 100, 1) : 0.0;

        return ['avg' => $avg, 'count' => $count, 'rate' => $rate];
    }

    /**
     * @return list<array{area: string, open: int, total_30d: int}>
     */
    public function areaComplaintRows(int $limit = 10): array
    {
        $since = now()->subDays(30);

        $openByArea = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('customer_id')
            ->join('customers', 'customers.id', '=', 'support_tickets.customer_id')
            ->select('customers.area_id', DB::raw('COUNT(*) as open_count'))
            ->groupBy('customers.area_id')
            ->pluck('open_count', 'area_id');

        $totalByArea = SupportTicket::query()
            ->where('support_tickets.created_at', '>=', $since)
            ->whereNotNull('customer_id')
            ->join('customers', 'customers.id', '=', 'support_tickets.customer_id')
            ->select('customers.area_id', DB::raw('COUNT(*) as total'))
            ->groupBy('customers.area_id')
            ->pluck('total', 'area_id');

        $areaIds = $openByArea->keys()->merge($totalByArea->keys())->unique()->filter();
        $areas = Area::query()->whereIn('id', $areaIds)->pluck('name', 'id');

        $rows = [];
        foreach ($areaIds as $areaId) {
            $rows[] = [
                'area' => $areas[$areaId] ?? 'Area #'.$areaId,
                'open' => (int) ($openByArea[$areaId] ?? 0),
                'total_30d' => (int) ($totalByArea[$areaId] ?? 0),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['open'] <=> $a['open']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return list<array{olt: string, open: int, critical: int}>
     */
    public function oltComplaintRows(int $limit = 10): array
    {
        $rows = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('olt_device_id')
            ->selectRaw('olt_device_id, COUNT(*) as open_count, SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END) as critical_count', ['critical'])
            ->groupBy('olt_device_id')
            ->orderByDesc('open_count')
            ->limit($limit)
            ->get();

        $oltIds = $rows->pluck('olt_device_id')->filter()->all();
        $olts = Device::query()->whereIn('id', $oltIds)->pluck('display_name', 'id');

        return $rows->map(fn ($row): array => [
            'olt' => $olts[$row->olt_device_id] ?? 'OLT #'.$row->olt_device_id,
            'open' => (int) $row->open_count,
            'critical' => (int) $row->critical_count,
        ])->all();
    }

    /**
     * @return list<array{name: string, resolved: int, open: int, avg_hours: float, sla_pct: float, csat: float}>
     */
    public function technicianRanking(int $days = 30, int $limit = 12): array
    {
        $since = now()->subDays($days);

        return User::query()
            ->whereIn('id', function ($q) use ($since): void {
                $q->select('assigned_to')
                    ->from('support_tickets')
                    ->whereNotNull('assigned_to')
                    ->where('updated_at', '>=', $since);
            })
            ->orderBy('name')
            ->limit($limit * 2)
            ->get(['id', 'name'])
            ->map(function (User $user) use ($since): array {
                $base = SupportTicket::query()->where('assigned_to', $user->id);
                $resolved = (clone $base)
                    ->where('resolved_at', '>=', $since)
                    ->whereNotNull('resolved_at')
                    ->get(['created_at', 'resolved_at', 'sla_resolve_due_at', 'customer_rating']);

                $avgHours = 0.0;
                if ($resolved->isNotEmpty()) {
                    $avgHours = round(
                        $resolved->avg(fn (SupportTicket $t): float => (float) $t->created_at?->diffInMinutes($t->resolved_at) / 60),
                        1,
                    );
                }

                $slaMet = $resolved->filter(
                    fn (SupportTicket $t): bool => $t->sla_resolve_due_at !== null
                        && $t->resolved_at !== null
                        && $t->resolved_at->lte($t->sla_resolve_due_at),
                )->count();

                $slaPct = $resolved->count() > 0 ? round(($slaMet / $resolved->count()) * 100, 1) : 0.0;
                $rated = $resolved->whereNotNull('customer_rating');
                $csat = $rated->isNotEmpty() ? round($rated->avg('customer_rating'), 1) : 0.0;

                return [
                    'name' => $user->name,
                    'resolved' => $resolved->count(),
                    'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
                    'avg_hours' => $avgHours,
                    'sla_pct' => $slaPct,
                    'csat' => $csat,
                ];
            })
            ->sortByDesc('resolved')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, count: int, percent: float}>
     */
    public function categoryTrends(int $days = 30, int $limit = 10): array
    {
        $since = now()->subDays($days);
        $rows = SupportTicket::query()
            ->where('created_at', '>=', $since)
            ->select('issue_type', DB::raw('COUNT(*) as total'))
            ->groupBy('issue_type')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $sum = max(1, (int) $rows->sum('total'));

        return $rows->map(function ($row) use ($sum): array {
            $type = (string) ($row->issue_type ?? 'other');
            $count = (int) $row->total;

            return [
                'label' => SupportCategories::label($type),
                'count' => $count,
                'percent' => round(($count / $sum) * 100, 1),
            ];
        })->all();
    }
}
