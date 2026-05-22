<?php

namespace App\Support;

use App\Filament\Pages\AccountingHub;
use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\FixedAssetResource;
use App\Filament\Resources\InventorySaleResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\OltResource;
use App\Filament\Resources\PopBoxResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WarehouseResource;
use App\Support\Rbac\StaffCapability;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class InventorySidebarRegistry
{
    /** Must match Filament NavigationGroup label «Inventory Pro» for sort + sidebar order. */
    public const GROUP_LABEL = 'Inventory Pro';

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   url: string,
     *   active_routes: list<string>,
     *   open_in_new_tab?: bool,
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'hub',
                'label' => 'Inventory center',
                'icon' => 'heroicon-o-cube',
                'sort' => 0,
                'url' => InventoryHub::getUrl(),
                'active_routes' => ['filament.admin.pages.inventory-hub'],
            ],
            [
                'key' => 'warehouses',
                'label' => 'Warehouses',
                'icon' => 'heroicon-o-building-library',
                'sort' => 1,
                'url' => WarehouseResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.warehouses.index',
                    'filament.admin.resources.warehouses.create',
                    'filament.admin.resources.warehouses.edit',
                ],
            ],
            [
                'key' => 'products',
                'label' => 'Products · barcode',
                'icon' => 'heroicon-o-shopping-bag',
                'sort' => 2,
                'url' => ProductResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.products.index',
                    'filament.admin.resources.products.create',
                    'filament.admin.resources.products.edit',
                ],
            ],
            [
                'key' => 'pos_new',
                'label' => 'New sale (POS)',
                'icon' => 'heroicon-o-qr-code',
                'sort' => 3,
                'url' => InventorySaleResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.inventory-sales.create'],
            ],
            [
                'key' => 'retail_sales',
                'label' => 'Retail sales',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 4,
                'url' => InventorySaleResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.inventory-sales.index',
                    'filament.admin.resources.inventory-sales.create',
                    'filament.admin.resources.inventory-sales.view',
                ],
            ],
            [
                'key' => 'purchase_orders',
                'label' => 'Purchase orders',
                'icon' => 'heroicon-o-clipboard-document-check',
                'sort' => 5,
                'url' => PurchaseOrderResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.purchase-orders.index',
                    'filament.admin.resources.purchase-orders.create',
                    'filament.admin.resources.purchase-orders.edit',
                ],
            ],
            [
                'key' => 'stock_ledger',
                'label' => 'Stock ledger',
                'icon' => 'heroicon-o-arrow-path',
                'sort' => 6,
                'url' => StockMovementResource::getUrl(),
                'active_routes' => ['filament.admin.resources.stock-movements.index'],
            ],
            [
                'key' => 'invoices_hardware',
                'label' => 'Invoices · hardware line',
                'icon' => 'heroicon-o-cpu-chip',
                'sort' => 7,
                'url' => InvoiceResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.invoices.index',
                    'filament.admin.resources.invoices.create',
                    'filament.admin.resources.invoices.edit',
                    'filament.admin.resources.invoices.due',
                    'filament.admin.resources.invoices.paid',
                ],
            ],
            [
                'key' => 'public_shop',
                'label' => 'Public shop',
                'icon' => 'heroicon-o-globe-alt',
                'sort' => 8,
                'url' => route('shop.index'),
                'active_routes' => [],
                'open_in_new_tab' => true,
            ],
            [
                'key' => 'devices',
                'label' => 'Devices / ONU',
                'icon' => 'heroicon-o-wifi',
                'sort' => 9,
                'url' => DeviceResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.devices.index',
                    'filament.admin.resources.devices.create',
                    'filament.admin.resources.devices.edit',
                    'filament.admin.resources.devices.view',
                ],
            ],
            [
                'key' => 'vendors',
                'label' => 'Vendors',
                'icon' => 'heroicon-o-building-storefront',
                'sort' => 11,
                'url' => VendorResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.vendors.index',
                    'filament.admin.resources.vendors.create',
                    'filament.admin.resources.vendors.edit',
                ],
            ],
            [
                'key' => 'accounting',
                'label' => 'Accounting · COGS',
                'icon' => 'heroicon-o-calculator',
                'sort' => 12,
                'url' => AccountingHub::getUrl(),
                'active_routes' => ['filament.admin.pages.accounting-hub'],
            ],
            [
                'key' => 'olts',
                'label' => 'OLTs',
                'icon' => 'heroicon-o-signal',
                'sort' => 13,
                'url' => OltResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.olts.index',
                    'filament.admin.resources.olts.create',
                    'filament.admin.resources.olts.edit',
                ],
            ],
            [
                'key' => 'pop_boxes',
                'label' => 'POP / boxes',
                'icon' => 'heroicon-o-map-pin',
                'sort' => 14,
                'url' => PopBoxResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.pop-boxes.index',
                    'filament.admin.resources.pop-boxes.create',
                    'filament.admin.resources.pop-boxes.edit',
                ],
            ],
            [
                'key' => 'fixed_assets',
                'label' => 'Fixed assets',
                'icon' => 'heroicon-o-building-office',
                'sort' => 15,
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

            $item = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group(self::GROUP_LABEL)
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    if ($entry['active_routes'] === []) {
                        return false;
                    }
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });

            if (! empty($entry['open_in_new_tab'])) {
                $item->openUrlInNewTab();
            }

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
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $cap = StaffCapability::for($user);

        if ($cap->isTenantAdmin()) {
            return match ($key) {
                'public_shop' => (bool) config('inventory.shop_enabled', true),
                default => true,
            };
        }

        return match ($key) {
            'hub' => $cap->canInventory(),
            'warehouses', 'products', 'pos_new', 'retail_sales', 'purchase_orders', 'stock_ledger' => $cap->canInventory(),
            'invoices_hardware' => $cap->canInventory() && $cap->canBilling(),
            'public_shop' => $cap->canInventory() && config('inventory.shop_enabled', true),
            'devices', 'olts', 'pop_boxes' => $cap->canInventory() || $cap->canAccessModuleGroup('Network'),
            'vendors', 'fixed_assets' => $cap->canInventory(),
            'accounting' => $cap->canInventory() && $cap->canAccounting(),
            default => false,
        };
    }
}
