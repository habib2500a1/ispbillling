<?php

namespace App\Support;

use App\Filament\Pages\BroadcastOutage;
use App\Filament\Pages\CallCenterHub;
use App\Filament\Pages\SalesLeadPipeline;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\KnowledgeArticleResource;
use App\Models\SalesLead;
use App\Support\SalesLeadPanelAccess;
use App\Filament\Resources\OutageResource;
use App\Filament\Resources\SalesLeadResource;
use App\Filament\Resources\SupportAssignmentRuleResource;
use App\Filament\Resources\SupportTicketResource;
use Illuminate\Support\Facades\Auth;
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
                'key' => 'support_center',
                'label' => 'Support center',
                'icon' => 'heroicon-o-lifebuoy',
                'sort' => 0,
                'url' => SupportHub::getUrl(),
                'active_routes' => ['filament.admin.pages.support-hub'],
            ],
            [
                'key' => 'call_center',
                'label' => 'Call center',
                'icon' => 'heroicon-o-phone',
                'sort' => 0.5,
                'url' => CallCenterHub::getUrl(),
                'active_routes' => [
                    'filament.admin.pages.call-center-hub',
                    'filament.admin.pages.call-center-reports',
                    'filament.admin.pages.manage-call-center-settings',
                    'filament.admin.resources.call-logs.index',
                    'filament.admin.resources.call-follow-ups.index',
                    'filament.admin.resources.voice-templates.index',
                    'filament.admin.resources.voice-sms-campaigns.index',
                ],
            ],
            [
                'key' => 'leads',
                'label' => 'New connections (portal)',
                'icon' => 'heroicon-o-user-plus',
                'sort' => 1,
                'url' => SalesLeadResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.sales-leads.index',
                    'filament.admin.resources.sales-leads.create',
                    'filament.admin.resources.sales-leads.edit',
                ],
            ],
            [
                'key' => 'pipeline',
                'label' => 'Connection pipeline',
                'icon' => 'heroicon-o-view-columns',
                'sort' => 2,
                'url' => SalesLeadPipeline::getUrl(),
                'active_routes' => ['filament.admin.pages.sales-lead-pipeline'],
            ],
            [
                'key' => 'tickets',
                'label' => 'Tickets',
                'icon' => 'heroicon-o-ticket',
                'sort' => 3,
                'url' => SupportTicketResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.support-tickets.index',
                    'filament.admin.resources.support-tickets.create',
                    'filament.admin.resources.support-tickets.edit',
                    'filament.admin.resources.support-tickets.view',
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

            $item = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Support')
                ->sort($entry['sort']);

            if ($entry['key'] === 'leads') {
                $newCount = SalesLead::query()->where('status', SalesLead::STATUS_NEW)->count();
                if ($newCount > 0) {
                    $item->badge((string) $newCount, 'warning');
                }
            }

            $item->isActiveWhen(function () use ($entry): bool {
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });

            $items[] = $item;
        }

        return $items;
    }

    public static function hasVisibleEntries(): bool
    {
        foreach (self::definitions() as $entry) {
            if (self::canSeeEntry($entry['key'])) {
                return true;
            }
        }

        return false;
    }

    public static function canSeeEntry(string $key): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        return match ($key) {
            'support_center' => SupportHub::canAccess(),
            'call_center' => CallCenterHub::canAccess(),
            'tickets' => SupportPanelAccess::viewTickets($user),
            'pipeline', 'leads' => SalesLeadPanelAccess::canView(),
            'broadcast_outage' => BroadcastOutage::canAccess(),
            'outages' => OutageResource::canViewAny(),
            'knowledge' => KnowledgeArticleResource::canViewAny(),
            'auto_assign' => SupportAssignmentRuleResource::canViewAny(),
            default => false,
        };
    }
}
