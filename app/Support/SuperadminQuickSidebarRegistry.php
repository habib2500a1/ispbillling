<?php

namespace App\Support;

use App\Models\User;
use App\Filament\Pages\DashboardHub;
use App\Filament\Pages\NocWall;
use App\Filament\Pages\OperationsHub;
use App\Filament\Pages\PaymentsOverview;
use App\Filament\Pages\SubscriberListsHub;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;

final class SuperadminQuickSidebarRegistry
{
    public const GROUP_LABEL = 'Overview';

    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'dashboard_hub',
                'label' => 'Dashboard hub',
                'icon' => 'heroicon-o-presentation-chart-line',
                'sort' => 1,
                'url' => DashboardHub::getUrl(),
                'active_routes' => ['filament.admin.pages.dashboard-hub'],
            ],
            [
                'key' => 'operations_center',
                'label' => 'Operations center',
                'icon' => 'heroicon-o-squares-plus',
                'sort' => 2,
                'url' => OperationsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.operations-hub'],
            ],
            [
                'key' => 'subscriber_lists',
                'label' => 'Subscriber lists',
                'icon' => 'heroicon-o-queue-list',
                'sort' => 3,
                'url' => SubscriberListsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.subscriber-lists-hub'],
            ],
            [
                'key' => 'payments_hub',
                'label' => 'Payments hub',
                'icon' => 'heroicon-o-credit-card',
                'sort' => 4,
                'url' => PaymentsOverview::getUrl(),
                'active_routes' => ['filament.admin.pages.payments-overview'],
            ],
            [
                'key' => 'noc_wall',
                'label' => 'NOC wall',
                'icon' => 'heroicon-o-tv',
                'sort' => 5,
                'url' => NocWall::getUrl(),
                'active_routes' => ['filament.admin.pages.noc-wall'],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null || ! self::userCanSee()) {
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
                ->group(self::GROUP_LABEL)
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

    public static function hasVisibleEntries(): bool
    {
        foreach (self::definitions() as $entry) {
            if (self::canSeeEntry($entry['key'])) {
                return true;
            }
        }

        return false;
    }

    public static function userCanSee(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user !== null && $user->hasRole('super-admin');
    }

    public static function canSeeEntry(string $key): bool
    {
        if (! self::userCanSee()) {
            return false;
        }

        return match ($key) {
            'dashboard_hub' => DashboardHub::canAccess(),
            'operations_center' => OperationsHub::canAccess(),
            'subscriber_lists' => SubscriberListsHub::canAccess(),
            'payments_hub' => PaymentsOverview::canAccess(),
            'noc_wall' => NocWall::canAccess(),
            default => false,
        };
    }
}
