<?php

namespace App\Services\IspOs;

use App\Models\FiberFaultLog;
use App\Models\NetworkEvent;
use App\Models\SignalAlert;
use App\Models\SupportTicket;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Schema;

final class NetworkTimelineService
{
    /**
     * @return list<array{title: string, type: string, severity: string, at: string, sort: int}>
     */
    public function recent(?int $tenantId = null, int $limit = 30): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $events = [];

        if (Schema::hasTable('network_events')) {
            $events = array_merge($events, $this->fromNetworkEvents($tenantId));
        }

        $events = array_merge(
            $events,
            $this->fromFiberFaults($tenantId),
            $this->fromSignalAlerts($tenantId),
            $this->fromTickets($tenantId),
        );

        usort($events, fn (array $a, array $b): int => ($b['sort'] ?? 0) <=> ($a['sort'] ?? 0));

        return array_slice($events, 0, $limit);
    }

    /**
     * @return list<array{title: string, type: string, severity: string, at: string, sort: int}>
     */
    private function fromNetworkEvents(int $tenantId): array
    {
        return NetworkEvent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('occurred_at')
            ->limit(15)
            ->get()
            ->map(fn (NetworkEvent $e): array => [
                'title' => $e->title,
                'type' => $e->event_type,
                'severity' => $e->severity ?? 'info',
                'at' => $e->occurred_at?->diffForHumans() ?? '—',
                'sort' => $e->occurred_at?->timestamp ?? 0,
            ])
            ->all();
    }

    /**
     * @return list<array{title: string, type: string, severity: string, at: string, sort: int}>
     */
    private function fromFiberFaults(int $tenantId): array
    {
        return FiberFaultLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('detected_at')
            ->limit(10)
            ->get()
            ->map(fn (FiberFaultLog $f): array => [
                'title' => $f->description ?? 'Fiber fault detected',
                'type' => 'fiber_cut',
                'severity' => $f->severity ?? 'critical',
                'at' => $f->detected_at?->diffForHumans() ?? '—',
                'sort' => $f->detected_at?->timestamp ?? 0,
            ])
            ->all();
    }

    /**
     * @return list<array{title: string, type: string, severity: string, at: string, sort: int}>
     */
    private function fromSignalAlerts(int $tenantId): array
    {
        return SignalAlert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (SignalAlert $a): array => [
                'title' => $a->title ?? 'Signal loss',
                'type' => 'signal_loss',
                'severity' => $a->severity ?? 'warning',
                'at' => $a->created_at?->diffForHumans() ?? '—',
                'sort' => $a->created_at?->timestamp ?? 0,
            ])
            ->all();
    }

    /**
     * @return list<array{title: string, type: string, severity: string, at: string, sort: int}>
     */
    private function fromTickets(int $tenantId): array
    {
        return SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('priority', ['high', 'urgent'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (SupportTicket $t): array => [
                'title' => 'Ticket #'.$t->id.' — '.($t->subject ?? 'Support'),
                'type' => 'complaint',
                'severity' => $t->priority === 'urgent' ? 'critical' : 'warning',
                'at' => $t->created_at?->diffForHumans() ?? '—',
                'sort' => $t->created_at?->timestamp ?? 0,
            ])
            ->all();
    }
}
