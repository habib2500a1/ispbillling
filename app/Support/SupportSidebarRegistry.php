<?php

namespace App\Support;

use App\Filament\Pages\BroadcastOutage;
use App\Filament\Pages\SalesLeadPipeline;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\KnowledgeArticleResource;
use App\Filament\Resources\OutageResource;
use App\Filament\Resources\SalesLeadResource;
use App\Filament\Resources\SupportAssignmentRuleResource;
use App\Filament\Resources\SupportTicketResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class SupportSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'tickets',
                'label' => 'All tickets',
                'icon' => 'heroicon-o-ticket',
                'sort' => 1,
                'url' => SupportTicketResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.support-tickets.index',
                    'filament.admin.resources.support-tickets.create',
                    'filament.admin.resources.support-tickets.edit',
                    'filament.admin.resources.support-tickets.view',
                ],
            ],
            [
                'key' => 'new_ticket',
                'label' => 'New ticket',
                'icon' => 'heroicon-o-plus-circle',
                'sort' => 2,
                'url' => SupportTicketResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.support-tickets.create'],
            ],
            [
                'key' => 'pipeline',
                'label' => 'Connection pipeline',
                'icon' => 'heroicon-o-funnel',
                'sort' => 3,
                'url' => SalesLeadPipeline::getUrl(),
                'active_routes' => ['filament.admin.pages.sales-lead-pipeline'],
            ],
            [
                'key' => 'leads',
                'label' => 'New connections',
                'icon' => 'heroicon-o-user-plus',
                'sort' => 4,
                'url' => SalesLeadResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.sales-leads.index',
                    'filament.admin.resources.sales-leads.create',
                    'filament.admin.resources.sales-leads.edit',
                ],
            ],
            [
                'key' => 'broadcast_outage',
                'label' => 'Broadcast outage',
                'icon' => 'heroicon-o-megaphone',
                'sort' => 5,
                'url' => BroadcastOutage::getUrl(),
                'active_routes' => ['filament.admin.pages.broadcast-outage'],
            ],
            [
                'key' => 'outages',
                'label' => 'Outage history',
                'icon' => 'heroicon-o-signal-slash',
                'sort' => 6,
                'url' => OutageResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.outages.index',
                    'filament.admin.resources.outages.create',
                    'filament.admin.resources.outages.edit',
                ],
            ],
            [
                'key' => 'knowledge',
                'label' => 'Knowledge base',
                'icon' => 'heroicon-o-book-open',
                'sort' => 7,
                'url' => KnowledgeArticleResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.knowledge-articles.index',
                    'filament.admin.resources.knowledge-articles.create',
                    'filament.admin.resources.knowledge-articles.edit',
                ],
            ],
            [
                'key' => 'auto_assign',
                'label' => 'Auto-assign rules',
                'icon' => 'heroicon-o-arrows-right-left',
                'sort' => 8,
                'url' => SupportAssignmentRuleResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.support-assignment-rules.index',
                    'filament.admin.resources.support-assignment-rules.create',
                    'filament.admin.resources.support-assignment-rules.edit',
                ],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null) {
            return [];
        }

        $items = [];

        foreach (self::definitions() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Support')
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });
        }

        return $items;
    }

    public static function canSeeEntry(string $key): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return match ($key) {
            'tickets', 'new_ticket' => SupportPanelAccess::viewTickets($user),
            'pipeline' => SalesLeadPipeline::canAccess(),
            'leads' => SalesLeadResource::canViewAny(),
            'broadcast_outage' => BroadcastOutage::canAccess(),
            'outages' => OutageResource::canViewAny(),
            'knowledge' => KnowledgeArticleResource::canViewAny(),
            'auto_assign' => SupportAssignmentRuleResource::canViewAny(),
            default => false,
        };
    }
}
