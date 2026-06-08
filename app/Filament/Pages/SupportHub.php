<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Pages\Concerns\ProvidesSupportAnalytics;
use App\Filament\Resources\KnowledgeArticleResource;
use App\Filament\Resources\OutageResource;
use App\Filament\Resources\SupportAssignmentRuleResource;
use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Support\SupportPanelAccess;
use Filament\Pages\Page;

class SupportHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;
    use ProvidesSupportAnalytics;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static string $view = 'filament.pages.support-hub';

    protected static ?string $navigationLabel = 'Support center';

    protected static ?string $title = '';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        return '';
    }

    public static function canAccess(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-support-module',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        return $this->cachedHubStats(function (): array {
            $base = SupportTicket::query();
            $open = (clone $base)->whereNotIn('status', ['resolved', 'closed']);

            return [
                'open' => (clone $open)->count(),
                'pending' => (clone $open)->where('status', 'pending')->count(),
                'closed' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
                'breached' => (clone $open)->whereNotNull('sla_resolve_due_at')->where('sla_resolve_due_at', '<', now())->count(),
                'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
                'critical' => (clone $open)->where('priority', 'critical')->count(),
                'escalated' => (clone $open)->where('escalation_level', '>', 0)->count(),
                'live_chat' => (clone $open)->where('channel', 'live_chat')->count(),
                'due_today' => (clone $open)->whereDate('sla_resolve_due_at', today())->count(),
                'active_technicians' => (clone $open)->whereNotNull('assigned_to')->distinct('assigned_to')->count('assigned_to'),
            ];
        });
    }

    /**
     * @return list<array{label: string, value: string, hint: string, url: string, tone: string}>
     */
    public function getKpiCards(): array
    {
        $s = $this->getStats();
        $ticketsUrl = SupportTicketResource::getUrl('index');

        return [
            [
                'label' => 'Open tickets',
                'value' => number_format($s['open']),
                'hint' => 'Active queue',
                'url' => $ticketsUrl.'?'.http_build_query(['activeTab' => 'open']),
                'tone' => 'open',
            ],
            [
                'label' => 'SLA breaches',
                'value' => number_format($s['breached']),
                'hint' => 'Past deadline',
                'url' => $ticketsUrl.'?'.http_build_query(['activeTab' => 'sla']),
                'tone' => 'breach',
            ],
            [
                'label' => 'Critical',
                'value' => number_format($s['critical']),
                'hint' => 'Highest priority',
                'url' => $ticketsUrl,
                'tone' => 'critical',
            ],
            [
                'label' => 'Escalated',
                'value' => number_format($s['escalated']),
                'hint' => 'Level > 0',
                'url' => $ticketsUrl,
                'tone' => 'breach',
            ],
            [
                'label' => 'Pending',
                'value' => number_format($s['pending']),
                'hint' => 'Awaiting customer',
                'url' => $ticketsUrl,
                'tone' => 'open',
            ],
            [
                'label' => 'Active technicians',
                'value' => number_format($s['active_technicians']),
                'hint' => 'With open assignments',
                'url' => TechnicianDashboard::getUrl(),
                'tone' => 'chat',
            ],
        ];
    }

    /**
     * @return list<array{title: string, message: string, url: string, severity: string}>
     */
    public function getActionInbox(): array
    {
        $items = [];
        $indexUrl = SupportTicketResource::getUrl('index');

        SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('sla_resolve_due_at')
            ->where('sla_resolve_due_at', '<', now())
            ->with('customer:id,name,customer_code')
            ->orderBy('sla_resolve_due_at')
            ->limit(6)
            ->get(['id', 'ticket_number', 'subject', 'customer_id', 'sla_resolve_due_at', 'priority'])
            ->each(function (SupportTicket $ticket) use (&$items): void {
                $items[] = [
                    'title' => $ticket->ticket_number.' · SLA overdue',
                    'message' => ($ticket->customer?->name ?? '—').' — '.$ticket->subject,
                    'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
                    'severity' => 'critical',
                ];
            });

        SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNull('assigned_to')
            ->where('priority', 'critical')
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get(['id', 'ticket_number', 'subject', 'customer_id'])
            ->each(function (SupportTicket $ticket) use (&$items): void {
                $items[] = [
                    'title' => $ticket->ticket_number.' · Unassigned critical',
                    'message' => ($ticket->customer?->name ?? '—').' — '.$ticket->subject,
                    'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
                    'severity' => 'warning',
                ];
            });

        if ($this->getStats()['live_chat'] > 0) {
            $items[] = [
                'title' => 'Live chat queue',
                'message' => number_format($this->getStats()['live_chat']).' portal chat session(s) waiting',
                'url' => $indexUrl.'?'.http_build_query(['activeTab' => 'live_chat']),
                'severity' => 'warning',
            ];
        }

        return array_slice($items, 0, 10);
    }

    /**
     * @return list<array{department: string, label: string, open: int, breached: int, unassigned: int}>
     */
    public function getSlaByDepartment(): array
    {
        $rows = [];
        foreach (SupportTicket::DEPARTMENTS as $key => $label) {
            $dept = SupportTicket::query()
                ->where('department', $key)
                ->whereNotIn('status', ['resolved', 'closed']);

            $rows[] = [
                'department' => $key,
                'label' => $label,
                'open' => (clone $dept)->count(),
                'breached' => (clone $dept)->whereNotNull('sla_resolve_due_at')->where('sla_resolve_due_at', '<', now())->count(),
                'unassigned' => (clone $dept)->whereNull('assigned_to')->count(),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['breached'] <=> $a['breached']);

        return $rows;
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, featured?: bool}>
     */
    public function getToolCards(): array
    {
        $cards = [
            [
                'title' => 'All tickets',
                'desc' => 'Queue · filters · bulk assign · SLA',
                'url' => SupportTicketResource::getUrl('index'),
                'icon' => 'heroicon-o-ticket',
                'featured' => true,
            ],
            [
                'title' => 'New ticket',
                'desc' => 'Phone, walk-in, or internal complaint',
                'url' => SupportTicketResource::getUrl('create'),
                'icon' => 'heroicon-o-plus-circle',
                'featured' => true,
            ],
            [
                'title' => 'Call center',
                'desc' => 'SIP desk · logs · follow-ups · reports',
                'url' => CallCenterHub::getUrl(),
                'icon' => 'heroicon-o-phone',
            ],
            [
                'title' => 'Task kanban',
                'desc' => 'Internal staff work items',
                'url' => TaskKanbanBoard::getUrl(),
                'icon' => 'heroicon-o-view-columns',
            ],
            [
                'title' => 'Field technicians',
                'desc' => 'Visits · GIS map · mobile queue',
                'url' => FieldTechnicianCenter::getUrl(),
                'icon' => 'heroicon-o-map-pin',
            ],
            [
                'title' => 'Broadcast outage',
                'desc' => 'SMS / email area notice',
                'url' => BroadcastOutage::getUrl(),
                'icon' => 'heroicon-o-megaphone',
            ],
            [
                'title' => 'Outage history',
                'desc' => 'Active and past area outages',
                'url' => OutageResource::getUrl('index'),
                'icon' => 'heroicon-o-signal-slash',
            ],
            [
                'title' => 'Knowledge base',
                'desc' => 'Portal help articles',
                'url' => KnowledgeArticleResource::getUrl('index'),
                'icon' => 'heroicon-o-book-open',
            ],
            [
                'title' => 'Auto-assign rules',
                'desc' => 'Area and department routing',
                'url' => SupportAssignmentRuleResource::getUrl('index'),
                'icon' => 'heroicon-o-arrows-right-left',
            ],
        ];

        if (TechnicianDashboard::canAccess()) {
            $cards[] = [
                'title' => 'Technician dashboard',
                'desc' => 'My assigned tickets and due today',
                'url' => TechnicianDashboard::getUrl(),
                'icon' => 'heroicon-o-user-circle',
            ];
        }

        return $cards;
    }

}
