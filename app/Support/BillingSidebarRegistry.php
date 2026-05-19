<?php

namespace App\Support;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\CollectionDeskReport;
use App\Filament\Pages\CollectorMobile;
use App\Filament\Pages\ManageCollectionDiscountSettings;
use App\Filament\Pages\CollectorVisitsReport;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\InvoiceResource;
use App\Services\Billing\BillingInvoiceCounts;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class BillingSidebarRegistry
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   count_key?: string,
     *   url: string,
     *   active_routes: list<string>,
     * }>
     */
    public static function items(): array
    {
        return array_map(
            static fn (array $item): array => [...$item, 'url' => static::resolveUrl($item)],
            static::definitions(),
        );
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   count_key?: string,
     *   url_target: string,
     *   active_routes: list<string>,
     * }>
     */
    private static function definitions(): array
    {
        return [
            [
                'key' => 'all_bills',
                'label' => 'All bills',
                'icon' => 'heroicon-o-queue-list',
                'sort' => 1,
                'count_key' => 'all',
                'url_target' => 'invoices.index',
                'active_routes' => [
                    'filament.admin.resources.invoices.index',
                ],
            ],
            [
                'key' => 'due_bills',
                'label' => 'Due bills',
                'icon' => 'heroicon-o-exclamation-triangle',
                'sort' => 2,
                'count_key' => 'due',
                'url_target' => 'invoices.due',
                'active_routes' => [
                    'filament.admin.resources.invoices.due',
                ],
            ],
            [
                'key' => 'paid_bills',
                'label' => 'Paid bills',
                'icon' => 'heroicon-o-check-circle',
                'sort' => 3,
                'count_key' => 'paid',
                'url_target' => 'invoices.paid',
                'active_routes' => [
                    'filament.admin.resources.invoices.paid',
                ],
            ],
            [
                'key' => 'new_invoice',
                'label' => 'New invoice',
                'icon' => 'heroicon-o-document-plus',
                'sort' => 4,
                'url_target' => 'invoices.create',
                'active_routes' => [
                    'filament.admin.resources.invoices.create',
                ],
            ],
            [
                'key' => 'collection_desk',
                'label' => 'Bill collection',
                'icon' => 'heroicon-o-currency-bangladeshi',
                'sort' => 5,
                'url_target' => 'collection.desk',
                'active_routes' => ['filament.admin.pages.bill-collection-desk'],
            ],
            [
                'key' => 'collector_mobile',
                'label' => 'Collector mobile',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 6,
                'url_target' => 'collector.mobile',
                'active_routes' => ['filament.admin.pages.collector-mobile'],
            ],
            [
                'key' => 'collection_discount_settings',
                'label' => 'Collection discounts',
                'icon' => 'heroicon-o-receipt-percent',
                'sort' => 7,
                'url_target' => 'collection.discount_settings',
                'active_routes' => ['filament.admin.pages.manage-collection-discount-settings'],
            ],
            [
                'key' => 'coupons',
                'label' => 'Coupons',
                'icon' => 'heroicon-o-ticket',
                'sort' => 8,
                'url_target' => 'coupons.index',
                'active_routes' => [
                    'filament.admin.resources.coupons.index',
                    'filament.admin.resources.coupons.create',
                    'filament.admin.resources.coupons.edit',
                ],
            ],
            [
                'key' => 'today_collection',
                'label' => "Today's collection",
                'icon' => 'heroicon-o-calendar-days',
                'sort' => 9,
                'count_key' => 'today_collection',
                'url_target' => 'collection.today',
                'active_routes' => [
                    'filament.admin.pages.collection-desk-report',
                ],
            ],
            [
                'key' => 'all_collection',
                'label' => 'All collection',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 10,
                'url_target' => 'collection.month',
                'active_routes' => [
                    'filament.admin.pages.collection-desk-report',
                ],
            ],
            [
                'key' => 'collector_visits',
                'label' => 'Collector visits',
                'icon' => 'heroicon-o-map-pin',
                'sort' => 11,
                'url_target' => 'collector.visits',
                'active_routes' => ['filament.admin.pages.collector-visits-report'],
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

        try {
            $counts = app(BillingInvoiceCounts::class)->all();
        } catch (\Throwable) {
            $counts = [];
        }

        $items = [];

        foreach (self::items() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $count = isset($entry['count_key']) ? ($counts[$entry['count_key']] ?? 0) : 0;
            $routes = $entry['active_routes'];

            $item = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Billing')
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($routes, $entry): bool {
                    if (! request()->routeIs($routes)) {
                        return false;
                    }

                    if ($entry['key'] === 'today_collection') {
                        return request()->query('preset', 'today') === 'today';
                    }

                    if ($entry['key'] === 'all_collection') {
                        return request()->query('preset') === 'month';
                    }

                    return true;
                });

            if ($count > 0 && isset($entry['count_key'])) {
                $item->badge((string) $count);
            }

            $items[] = $item;
        }

        return $items;
    }

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'collection_desk' => BillCollectionDesk::canAccess(),
            'collection_discount_settings' => ManageCollectionDiscountSettings::canAccess(),
            'collector_mobile' => CollectorMobile::canAccess(),
            'collector_visits' => CollectorVisitsReport::canAccess(),
            'coupons' => CouponResource::canViewAny(),
            'new_invoice' => InvoiceResource::canCreate(),
            default => InvoiceResource::canViewAny(),
        };
    }

    /**
     * @param  array{url_target: string}  $item
     */
    private static function resolveUrl(array $item): string
    {
        return match ($item['url_target']) {
            'invoices.index' => InvoiceResource::getUrl('index'),
            'invoices.due' => InvoiceResource::getUrl('due'),
            'invoices.paid' => InvoiceResource::getUrl('paid'),
            'invoices.create' => InvoiceResource::getUrl('create'),
            'collection.today' => CollectionDeskReport::getUrl(['preset' => 'today']),
            'collection.month' => CollectionDeskReport::getUrl(['preset' => 'month']),
            'collection.desk' => BillCollectionDesk::getUrl(),
            'collection.discount_settings' => ManageCollectionDiscountSettings::getUrl(),
            'collector.mobile' => CollectorMobile::getUrl(),
            'collector.visits' => CollectorVisitsReport::getUrl(),
            'coupons.index' => CouponResource::getUrl(),
            default => '#',
        };
    }
}
