<?php

namespace App\Filament\Resources\SupportTicketResource\Pages\Concerns;

use App\Filament\Pages\SupportHub;
use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;

/**
 * Premium support ticket list chrome (UI only — preserves Filament table/tabs).
 */
trait UsesSupportTicketLayout
{
    public function mountSupportTicketLayout(): void
    {
        $tab = request()->query('activeTab');
        if (is_string($tab) && $tab !== '') {
            $this->activeTab = $tab;
        }
    }

    /**
     * @return list<array{key: string, label: string, count: int|null, url: string, active: bool}>
     */
    public function getSupportQueueChips(): array
    {
        $base = SupportTicket::query();
        $active = (string) ($this->activeTab ?? 'all');
        $indexUrl = SupportTicketResource::getUrl('index');

        $chips = [
            ['key' => 'all', 'label' => 'All', 'count' => null],
            ['key' => 'open', 'label' => 'Open', 'count' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->count()],
            ['key' => 'sla', 'label' => 'SLA breach', 'count' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->whereNotNull('sla_resolve_due_at')->where('sla_resolve_due_at', '<', now())->count()],
            ['key' => 'unassigned', 'label' => 'Unassigned', 'count' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->whereNull('assigned_to')->count()],
            ['key' => 'mine', 'label' => 'Mine', 'count' => (clone $base)->where('assigned_to', auth()->id())->whereNotIn('status', ['resolved', 'closed'])->count()],
            ['key' => 'live_chat', 'label' => 'Live chat', 'count' => (clone $base)->where('channel', 'live_chat')->whereNotIn('status', ['resolved', 'closed'])->count()],
        ];

        return array_map(static function (array $chip) use ($active, $indexUrl): array {
            $key = $chip['key'];

            return [
                ...$chip,
                'url' => $key === 'all' ? $indexUrl : $indexUrl.'?'.http_build_query(['activeTab' => $key]),
                'active' => $active === $key || ($key === 'all' && $active === ''),
            ];
        }, $chips);
    }

    /**
     * @return list<array{label: string, value: string, hint?: string}>
     */
    public function getSupportListStats(): array
    {
        $base = SupportTicket::query();

        return [
            ['label' => 'Open', 'value' => number_format((clone $base)->whereNotIn('status', ['resolved', 'closed'])->count()), 'hint' => 'Active queue'],
            ['label' => 'In progress', 'value' => number_format((clone $base)->where('status', 'in_progress')->count()), 'hint' => 'Field / NOC'],
            ['label' => 'Resolved today', 'value' => number_format((clone $base)->whereDate('resolved_at', today())->count()), 'hint' => 'Today'],
            ['label' => 'Critical', 'value' => number_format((clone $base)->where('priority', 'critical')->whereNotIn('status', ['resolved', 'closed'])->count()), 'hint' => 'P1'],
            ['label' => 'SLA breached', 'value' => number_format((clone $base)->whereNotIn('status', ['resolved', 'closed'])->whereNotNull('sla_resolve_due_at')->where('sla_resolve_due_at', '<', now())->count()), 'hint' => 'Overdue'],
        ];
    }

    /**
     * @return list<array{url: string, label: string, icon: string, active?: bool}>
     */
    public function getSupportDockLinks(): array
    {
        return [
            ['url' => SupportHub::getUrl(), 'label' => 'Center', 'icon' => 'heroicon-o-lifebuoy'],
            ['url' => SupportTicketResource::getUrl('index'), 'label' => 'Tickets', 'icon' => 'heroicon-o-ticket', 'active' => true],
            ['url' => SupportTicketResource::getUrl('create'), 'label' => 'New', 'icon' => 'heroicon-o-plus-circle', 'fullReload' => true],
            ['url' => SupportHub::getUrl(['tab' => 'tools']), 'label' => 'Tools', 'icon' => 'heroicon-o-wrench-screwdriver'],
        ];
    }
}
