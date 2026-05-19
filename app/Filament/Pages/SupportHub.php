<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use App\Models\SupportTicket;
use App\Support\SupportPanelAccess;
use Filament\Pages\Page;

class SupportHub extends Page
{
    use HidesHubNavigation;
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static string $view = 'filament.pages.support-hub';

    protected static ?string $navigationLabel = 'Support center';

    protected static ?string $title = 'Support center';

    protected static ?string $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $base = SupportTicket::query();

        return [
            'open' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'breached' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->whereNotNull('sla_resolve_due_at')
                ->where('sla_resolve_due_at', '<', now())
                ->count(),
            'unassigned' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->whereNull('assigned_to')
                ->count(),
            'live_chat' => (clone $base)->where('channel', 'live_chat')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
            'due_today' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])
                ->whereDate('sla_resolve_due_at', today())
                ->count(),
        ];
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
     * @return list<array{label: string, value: int|string, hint?: string, class?: string, valueClass?: string}>
     */
    public function getStatCards(): array
    {
        $stats = $this->getStats();

        return [
            [
                'label' => 'Open tickets',
                'value' => number_format($stats['open']),
                'hint' => 'Not resolved or closed',
                'class' => 'isp-hub-stat--amber',
            ],
            [
                'label' => 'SLA overdue',
                'value' => number_format($stats['breached']),
                'hint' => 'Past resolve deadline',
                'class' => $stats['breached'] > 0 ? 'isp-hub-stat--danger' : '',
                'valueClass' => $stats['breached'] > 0 ? 'isp-hub-stat-value--danger' : '',
            ],
            [
                'label' => 'Unassigned',
                'value' => number_format($stats['unassigned']),
                'hint' => 'Needs technician',
            ],
            [
                'label' => 'Live chat',
                'value' => number_format($stats['live_chat']),
                'hint' => 'Portal chat queue',
                'class' => $stats['live_chat'] > 0 ? 'isp-hub-stat--sky' : '',
            ],
            [
                'label' => 'Due today',
                'value' => number_format($stats['due_today']),
                'hint' => 'SLA resolves today',
                'class' => $stats['due_today'] > 0 ? 'isp-hub-stat--warn' : '',
            ],
        ];
    }
}
