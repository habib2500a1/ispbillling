<?php

namespace App\Support;

use App\Filament\Pages\ResellerPackagePricesPage;
use App\Filament\Pages\ResellerPendingWalletRechargesPage;
use App\Filament\Pages\ResellerCollectionPerformancePage;
use App\Filament\Pages\ResellerReportPage;
use App\Filament\Pages\ResellerWalletHubPage;
use App\Filament\Pages\ResellersHub;
use App\Filament\Resources\ResellerResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class ResellerSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'hub',
                'label' => 'Reseller center',
                'icon' => 'heroicon-o-building-storefront',
                'sort' => 0,
                'url' => ResellersHub::getUrl(),
                'active_routes' => ['filament.admin.pages.resellers-hub'],
            ],
            [
                'key' => 'add',
                'label' => 'Add reseller',
                'icon' => 'heroicon-o-plus-circle',
                'sort' => 1,
                'url' => ResellerResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.resellers.create'],
            ],
            [
                'key' => 'all',
                'label' => 'All resellers',
                'icon' => 'heroicon-o-users',
                'sort' => 2,
                'url' => ResellerResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.resellers.index',
                    'filament.admin.resources.resellers.edit',
                    'filament.admin.resources.resellers.view',
                ],
            ],
            [
                'key' => 'package_prices',
                'label' => 'Reseller packages',
                'icon' => 'heroicon-o-currency-dollar',
                'sort' => 3,
                'url' => ResellerPackagePricesPage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-package-prices'],
            ],
            [
                'key' => 'report',
                'label' => 'Commission report',
                'icon' => 'heroicon-o-chart-bar-square',
                'sort' => 4,
                'url' => ResellerReportPage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-report'],
            ],
            [
                'key' => 'collection_performance',
                'label' => 'Collection performance',
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 5,
                'url' => ResellerCollectionPerformancePage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-collection-performance'],
            ],
            [
                'key' => 'wallet',
                'label' => 'Wallet',
                'icon' => 'heroicon-o-wallet',
                'sort' => 6,
                'url' => ResellerWalletHubPage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-wallet-hub'],
            ],
            [
                'key' => 'pending_topups',
                'label' => 'Pending top-ups',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 7,
                'url' => ResellerPendingWalletRechargesPage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-pending-wallet-recharges'],
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
                ->group('Resellers')
                ->sort($entry['sort']);

            if ($entry['key'] === 'pending_topups') {
                $pending = ResellerPendingWalletRechargesPage::pendingCount();
                if ($pending > 0) {
                    $item->badge((string) $pending, color: 'warning');
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

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'hub' => ResellersHub::canAccess(),
            'package_prices' => ResellerPackagePricesPage::canAccess(),
            'report' => ResellerReportPage::canAccess(),
            'collection_performance' => ResellerCollectionPerformancePage::canAccess(),
            'wallet' => ResellerWalletHubPage::canAccess(),
            'pending_topups' => ResellerPendingWalletRechargesPage::canAccess(),
            'add' => ResellerResource::canCreate(),
            default => ResellerResource::canViewAny(),
        };
    }
}
