<?php

namespace App\Support;

use App\Filament\Pages\ResellerPackagePricesPage;
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
                'label' => 'Report',
                'icon' => 'heroicon-o-chart-bar-square',
                'sort' => 4,
                'url' => ResellerReportPage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-report'],
            ],
            [
                'key' => 'wallet',
                'label' => 'Wallet',
                'icon' => 'heroicon-o-wallet',
                'sort' => 5,
                'url' => ResellerWalletHubPage::getUrl(),
                'active_routes' => ['filament.admin.pages.reseller-wallet-hub'],
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
                ->group('Resellers')
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
        return match ($key) {
            'package_prices' => ResellerPackagePricesPage::canAccess(),
            'report' => ResellerReportPage::canAccess(),
            'wallet' => ResellerWalletHubPage::canAccess(),
            'add' => ResellerResource::canCreate(),
            default => ResellerResource::canViewAny(),
        };
    }
}
