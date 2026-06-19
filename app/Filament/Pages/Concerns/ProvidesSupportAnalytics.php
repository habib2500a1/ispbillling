<?php

namespace App\Filament\Pages\Concerns;

use App\Models\SupportTicket;
use App\Services\Support\SupportAnalyticsService;

/**
 * Read-only support analytics for hub UI.
 */
trait ProvidesSupportAnalytics
{
    protected function analyticsService(): SupportAnalyticsService
    {
        return app(SupportAnalyticsService::class);
    }

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

        $mttr = $this->analyticsService()->mttrStats(30);
        $csat = $this->analyticsService()->csatSummary(30);

        return [
            ['label' => 'MTTR (30d)', 'value' => $mttr[0]['value'], 'hint' => $mttr[0]['hint']],
            ['label' => 'MTFR (30d)', 'value' => $mttr[1]['value'], 'hint' => $mttr[1]['hint']],
            ['label' => 'SLA performance (30d)', 'value' => $slaPerformance.'%', 'hint' => 'Resolved within SLA'],
            ['label' => 'CSAT avg (30d)', 'value' => $csat['avg'] > 0 ? $csat['avg'].'/5' : '—', 'hint' => $csat['count'].' ratings ('.$csat['rate'].'%)'],
            ['label' => 'Resolution rate (30d)', 'value' => $resolutionRate.'%', 'hint' => $resolved.' / '.$created.' tickets'],
            ['label' => 'Open now', 'value' => number_format($open), 'hint' => $breached.' SLA breach'],
        ];
    }

    /**
     * @return list<array{label: string, count: int, percent: float}>
     */
    public function getCategoryTrends(): array
    {
        return $this->analyticsService()->categoryTrends(30, 10);
    }

    /**
     * @return list<array{name: string, resolved: int, open: int, avg_hours: float, sla_pct: float, csat: float}>
     */
    public function getTechnicianPerformance(): array
    {
        return $this->analyticsService()->technicianRanking(30, 12);
    }

    /**
     * @return list<array{area: string, open: int, total_30d: int}>
     */
    public function getAreaComplaints(): array
    {
        return $this->analyticsService()->areaComplaintRows(10);
    }

    /**
     * @return list<array{olt: string, open: int, critical: int}>
     */
    public function getOltComplaints(): array
    {
        return $this->analyticsService()->oltComplaintRows(10);
    }
}
