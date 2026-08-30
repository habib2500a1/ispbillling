<?php

namespace App\Services\Noc;

use App\Models\CustomerOnu;
use App\Models\Olt;
use App\Models\RouterList;
use App\Models\SupportTicket;
use App\Services\Olt\IspbillingOpticalBridge;
use App\Services\Support\TicketSlaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NOC wall payload — optical + routers + ticket SLA (Code Pagol).
 */
final class NocOverviewService
{
    public function payload(): array
    {
        try {
            $optical = $this->optical();
            $sla = app(TicketSlaService::class);
            $tickets = $sla->summaryCounts();

            $priorityOrder = "CASE LOWER(COALESCE(priority, '')) WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END";

            $breachedTickets = SupportTicket::query()
                ->whereIn('status', ['open', 'in_progress'])
                ->orderByRaw($priorityOrder)
                ->orderBy('created_at')
                ->limit(40)
                ->get()
                ->filter(fn (SupportTicket $t) => $sla->isResolveBreached($t) || $sla->isFirstResponseBreached($t))
                ->take(20)
                ->values()
                ->map(fn (SupportTicket $t) => $this->ticketRow($t, $sla));

            $recentOpen = SupportTicket::query()
                ->whereIn('status', ['open', 'in_progress'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (SupportTicket $t) => $this->ticketRow($t, $sla));

            return [
                'optical' => $optical,
                'network' => [
                    'routers' => RouterList::count(),
                    'routers_connected' => RouterList::where('action', 'connected')->count(),
                    'olts_local' => Olt::count(),
                    'onus_local' => CustomerOnu::count(),
                ],
                'tickets' => $tickets,
                'breached_tickets' => $breachedTickets,
                'recent_open' => $recentOpen,
                'updated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('noc overview payload failed', ['error' => $e->getMessage()]);

            return [
                'optical' => $this->optical(),
                'network' => [
                    'routers' => 0,
                    'routers_connected' => 0,
                    'olts_local' => 0,
                    'onus_local' => 0,
                ],
                'tickets' => ['open' => 0, 'in_progress' => 0, 'breached' => 0, 'high_open' => 0],
                'breached_tickets' => collect(),
                'recent_open' => collect(),
                'updated_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketRow(SupportTicket $t, TicketSlaService $sla): array
    {
        return [
            'ticket_no' => $t->ticket_no,
            'subject' => $t->subject,
            'priority' => $t->priority,
            'status' => $t->status,
            'customer' => $t->customer_unique_id ?: ($t->ppp_username ?: '—'),
            'sla' => $sla->statusLabel($t),
            'age' => optional($t->created_at)->diffForHumans(),
            'resolve_due' => $sla->resolveDueAt($t)->format('d M Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optical(): array
    {
        $data = [
            'bridge' => false,
            'olts' => 0,
            'onus' => 0,
            'linked' => 0,
            'rx_ok' => 0,
            'rx_weak' => 0,
            'rx_critical' => 0,
            'avg_rx' => null,
        ];

        try {
            $bridge = app(IspbillingOpticalBridge::class);
            $data['bridge'] = $bridge->enabled();
            if (! $bridge->enabled()) {
                return $data;
            }

            $row = DB::connection('ispbilling')->selectOne(
                <<<'SQL'
                SELECT
                    COUNT(*) FILTER (WHERE type = 'olt') AS olts,
                    COUNT(*) FILTER (WHERE type = 'onu') AS onus,
                    COUNT(*) FILTER (WHERE type = 'onu' AND customer_id IS NOT NULL) AS linked,
                    COUNT(*) FILTER (WHERE type = 'onu' AND rx_power_dbm IS NOT NULL AND rx_power_dbm > -25) AS rx_ok,
                    COUNT(*) FILTER (WHERE type = 'onu' AND rx_power_dbm IS NOT NULL AND rx_power_dbm <= -25 AND rx_power_dbm > -28) AS rx_weak,
                    COUNT(*) FILTER (WHERE type = 'onu' AND rx_power_dbm IS NOT NULL AND rx_power_dbm <= -28) AS rx_critical,
                    ROUND(AVG(rx_power_dbm) FILTER (WHERE type = 'onu' AND rx_power_dbm IS NOT NULL)::numeric, 2) AS avg_rx
                FROM devices
                SQL
            );

            if ($row) {
                $data['olts'] = (int) $row->olts;
                $data['onus'] = (int) $row->onus;
                $data['linked'] = (int) $row->linked;
                $data['rx_ok'] = (int) $row->rx_ok;
                $data['rx_weak'] = (int) $row->rx_weak;
                $data['rx_critical'] = (int) $row->rx_critical;
                $data['avg_rx'] = $row->avg_rx !== null ? (float) $row->avg_rx : null;
            }
        } catch (\Throwable $e) {
            Log::warning('noc optical overview failed', ['error' => $e->getMessage()]);
        }

        return $data;
    }
}
