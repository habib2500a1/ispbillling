<?php

namespace App\Filament\Pages\Concerns;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Read-only support analytics for hub UI.
 */
trait ProvidesSupportAnalytics
{
    /**
     * @return list<array{label: string, value: string, hint: string}>
     */
    public function getAnalyticsStats(): array
    {
        $since = now()->subDays(30);
        $created = SupportTicket::query()->where('created_at', '>=', $since)->count();
        $resolved = SupportTicket::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->count();

        $resolutionRate = $created > 0 ? round(($resolved / $created) * 100, 1) : 0;

        $open = SupportTicket::query()->whereNotIn('status', ['resolved', 'closed'])->count();
        $breached = SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_resolve_due_at')
            ->where('sla_resolve_due_at', '<', now())
            ->count();

        $slaMet = SupportTicket::query()
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('sla_resolve_due_at')
            ->whereColumn('resolved_at', '<=', 'sla_resolve_due_at')
            ->count();

        $slaTotal = SupportTicket::query()
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('sla_resolve_due_at')
            ->count();

        $slaPerformance = $slaTotal > 0 ? round(($slaMet / $slaTotal) * 100, 1) : 0;

        $resolvedTickets = SupportTicket::query()
            ->where('resolved_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        $avgHours = 0.0;
        if ($resolvedTickets->isNotEmpty()) {
            $avgHours = round(
                $resolvedTickets->avg(fn (SupportTicket $t): float => (float) $t->created_at?->diffInMinutes($t->resolved_at) / 60),
                1,
            );
        }

        return [
            ['label' => 'Resolution rate (30d)', 'value' => $resolutionRate.'%', 'hint' => $resolved.' / '.$created.' tickets'],
            ['label' => 'SLA performance (30d)', 'value' => $slaPerformance.'%', 'hint' => 'Resolved within SLA'],
            ['label' => 'Avg resolution', 'value' => $avgHours.'h', 'hint' => 'Last 30 days'],
            ['label' => 'Open now', 'value' => number_format($open), 'hint' => $breached.' SLA breach'],
        ];
    }

    /**
     * @return list<array{label: string, count: int, percent: float}>
     */
    public function getCategoryTrends(): array
    {
        $since = now()->subDays(30);
        $rows = SupportTicket::query()
            ->where('created_at', '>=', $since)
            ->select('issue_type', DB::raw('COUNT(*) as total'))
            ->groupBy('issue_type')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $sum = max(1, (int) $rows->sum('total'));

        return $rows->map(function ($row) use ($sum): array {
            $type = (string) ($row->issue_type ?? 'other');
            $count = (int) $row->total;

            return [
                'label' => SupportTicket::ISSUE_TYPES[$type] ?? ucfirst($type),
                'count' => $count,
                'percent' => round(($count / $sum) * 100, 1),
            ];
        })->all();
    }

    /**
     * @return list<array{name: string, resolved: int, open: int}>
     */
    public function getTechnicianPerformance(): array
    {
        $since = now()->subDays(30);

        return User::query()
            ->whereIn('id', function ($q) use ($since): void {
                $q->select('assigned_to')
                    ->from('support_tickets')
                    ->whereNotNull('assigned_to')
                    ->where('updated_at', '>=', $since);
            })
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name'])
            ->map(function (User $user) use ($since): array {
                $base = SupportTicket::query()->where('assigned_to', $user->id);

                return [
                    'name' => $user->name,
                    'resolved' => (clone $base)->where('resolved_at', '>=', $since)->count(),
                    'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
                ];
            })
            ->sortByDesc('resolved')
            ->values()
            ->all();
    }
}
