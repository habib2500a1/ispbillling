<?php

namespace App\Support;

use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\FixedAssetResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class InventorySidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'products',
                'label' => 'Products',
                'icon' => 'heroicon-o-shopping-bag',
                'sort' => 1,
                'url' => ProductResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.products.index',
                    'filament.admin.resources.products.create',
                    'filament.admin.resources.products.edit',
                ],
            ],
            [
                'key' => 'purchase_orders',
                'label' => 'Purchase orders',
                'icon' => 'heroicon-o-clipboard-document-check',
                'sort' => 2,
                'url' => PurchaseOrderResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.purchase-orders.index',
                    'filament.admin.resources.purchase-orders.create',
                    'filament.admin.resources.purchase-orders.edit',
                ],
            ],
            [
                'key' => 'fixed_assets',
                'label' => 'Fixed assets',
                'icon' => 'heroicon-o-building-office',
                'sort' => 3,
                'url' => FixedAssetResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.fixed-assets.index',
                    'filament.admin.resources.fixed-assets.create',
                    'filament.admin.resources.fixed-assets.edit',
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
                ->group('Inventory')
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

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'products' => ProductResource::canViewAny(),
            'purchase_orders' => PurchaseOrderResource::canViewAny(),
            'fixed_assets' => FixedAssetResource::canViewAny(),
            default => false,
        };
    }
}
