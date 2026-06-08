<?php

namespace App\Support;

use App\Filament\Pages\AccountsWalletHubPage;
use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\BillingFundFlowReport;
use App\Filament\Pages\BillingNoticesPage;
use App\Filament\Pages\BillingOverview;
use App\Services\Billing\AdminBillingNoticesService;
use App\Filament\Pages\PaymentsReport;
use App\Support\Rbac\StaffCapability;
use App\Filament\Pages\CollectionDeskReport;
use App\Filament\Pages\CollectorMobile;
use App\Filament\Pages\DunningReport;
use App\Filament\Pages\ManageCollectionDiscountSettings;
use App\Filament\Pages\ManagePaymentRenewalSettings;
use App\Filament\Pages\CollectorVisitsReport;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\PromotionalOfferResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\StaffExpenseResource;
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
                'key' => 'billing_center',
                'label' => 'Billing center',
                'icon' => 'heroicon-o-squares-2x2',
                'sort' => 0,
                'url_target' => 'billing.overview',
                'active_routes' => ['filament.admin.pages.billing-overview'],
            ],
            [
                'key' => 'billing_notices',
                'label' => 'Billing notices',
                'icon' => 'heroicon-o-bell-alert',
                'sort' => 0.5,
                'count_key' => 'notices',
                'url_target' => 'billing.notices',
                'active_routes' => ['filament.admin.pages.billing-notices'],
            ],
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
                'active_routes' => ['filament.admin.pages.bill-collection'],
            ],
            [
                'key' => 'payment_reports',
                'label' => 'Payment report (incl. bKash)',
                'icon' => 'heroicon-o-document-chart-bar',
                'sort' => 5.5,
                'url_target' => 'billing.payment_reports',
                'active_routes' => ['filament.admin.pages.payments-report'],
            ],
            [
                'key' => 'admin_wallets',
                'label' => 'Wallets (cash & bank)',
                'icon' => 'heroicon-o-wallet',
                'sort' => 5.7,
                'url_target' => 'billing.wallets',
                'active_routes' => ['filament.admin.pages.accounts-wallet-hub'],
            ],
            [
                'key' => 'payment_renewal_settings',
                'label' => 'Payment renew rules',
                'icon' => 'heroicon-o-arrow-path-rounded-square',
                'sort' => 6,
                'url_target' => 'collection.renewal_settings',
                'active_routes' => ['filament.admin.pages.payment-renewal-settings'],
            ],
            [
                'key' => 'collector_mobile',
                'label' => 'Collector mobile',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 7,
                'url_target' => 'collector.mobile',
                'active_routes' => ['filament.admin.pages.collector-mobile'],
            ],
            [
                'key' => 'collection_discount_settings',
                'label' => 'Collection discounts',
                'icon' => 'heroicon-o-receipt-percent',
                'sort' => 8,
                'url_target' => 'collection.discount_settings',
                'active_routes' => ['filament.admin.pages.manage-collection-discount-settings'],
            ],
            [
                'key' => 'bill_money_trail',
                'label' => 'Bill money trail',
                'icon' => 'heroicon-o-arrows-right-left',
                'sort' => 8,
                'url_target' => 'billing.fund_flow',
                'active_routes' => ['filament.admin.pages.billing-fund-flow-report'],
            ],
            [
                'key' => 'staff_expenses',
                'label' => 'Staff expenses',
                'icon' => 'heroicon-o-receipt-refund',
                'sort' => 9,
                'url_target' => 'billing.staff_expenses',
                'active_routes' => [
                    'filament.admin.resources.staff-expenses.index',
                    'filament.admin.resources.staff-expenses.create',
                    'filament.admin.resources.staff-expenses.view',
                ],
            ],
            [
                'key' => 'coupons',
                'label' => 'Coupons',
                'icon' => 'heroicon-o-ticket',
                'sort' => 10,
                'url_target' => 'coupons.index',
                'active_routes' => [
                    'filament.admin.resources.coupons.index',
                    'filament.admin.resources.coupons.create',
                    'filament.admin.resources.coupons.edit',
                ],
            ],
            [
                'key' => 'promotional_offers',
                'label' => 'Offers & promotions',
                'icon' => 'heroicon-o-gift',
                'sort' => 10.5,
                'url_target' => 'promotional_offers.index',
                'active_routes' => [
                    'filament.admin.resources.promotional-offers.index',
                    'filament.admin.resources.promotional-offers.create',
                    'filament.admin.resources.promotional-offers.edit',
                ],
            ],
            [
                'key' => 'collection_report',
                'label' => 'Collection report',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 11,
                'count_key' => 'today_collection',
                'url_target' => 'collection.report',
                'active_routes' => [
                    'filament.admin.pages.collection-desk-report',
                ],
            ],
            [
                'key' => 'dunning_report',
                'label' => 'Dunning report',
                'icon' => 'heroicon-o-bell-alert',
                'sort' => 11.5,
                'url_target' => 'billing.dunning',
                'active_routes' => ['filament.admin.pages.dunning-report'],
            ],
            [
                'key' => 'collector_visits',
                'label' => 'Collector visits',
                'icon' => 'heroicon-o-map-pin',
                'sort' => 13,
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

            $count = match ($entry['key'] ?? '') {
                'billing_notices' => app(AdminBillingNoticesService::class)->actionableCount(),
                default => isset($entry['count_key']) ? ($counts[$entry['count_key']] ?? 0) : 0,
            };
            $routes = $entry['active_routes'];

            $item = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Billing')
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($routes, $entry): bool {
                    if (isset($entry['active_when']) && is_callable($entry['active_when'])) {
                        return (bool) ($entry['active_when'])();
                    }

                    if (! request()->routeIs($routes)) {
                        return false;
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
        $user = auth()->user();
        if ($user && StaffCapability::for($user)->isTenantAdmin()) {
            return true;
        }

        return match ($key) {
            'billing_center' => BillingOverview::canAccess(),
            'billing_notices' => BillingNoticesPage::canAccess(),
            'bill_money_trail' => BillingFundFlowReport::canAccess(),
            'staff_expenses' => StaffExpenseResource::canViewAny(),
            'collection_desk' => BillCollectionDesk::canAccess(),
            'payment_reports' => PaymentsReport::canAccess(),
            'dunning_report' => DunningReport::canAccess(),
            'admin_wallets' => AccountsWalletHubPage::canAccess(),
            'collection_discount_settings' => ManageCollectionDiscountSettings::canAccess(),
            'payment_renewal_settings' => ManagePaymentRenewalSettings::canAccess(),
            'collector_mobile' => CollectorMobile::canAccess(),
            'collector_visits' => CollectorVisitsReport::canAccess(),
            'coupons' => CouponResource::canViewAny(),
            'promotional_offers' => PromotionalOfferResource::canViewAny(),
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
            'billing.overview' => BillingOverview::getUrl(),
            'billing.notices' => BillingNoticesPage::getUrl(),
            'billing.fund_flow' => BillingFundFlowReport::getUrl(),
            'billing.staff_expenses' => StaffExpenseResource::getUrl(),
            'invoices.index' => InvoiceResource::getUrl('index'),
            'invoices.due' => InvoiceResource::getUrl('due'),
            'invoices.paid' => InvoiceResource::getUrl('paid'),
            'invoices.create' => InvoiceResource::getUrl('create'),
            'collection.report' => CollectionDeskReport::getUrl(['preset' => 'today']),
            'billing.dunning' => DunningReport::getUrl(),
            'collection.desk' => BillCollectionDesk::getUrl(),
            'billing.payment_reports' => PaymentsReport::getUrl(),
            'billing.wallets' => AccountsWalletHubPage::getUrl(),
            'collection.discount_settings' => ManageCollectionDiscountSettings::getUrl(),
            'collection.renewal_settings' => ManagePaymentRenewalSettings::getUrl(),
            'collector.mobile' => CollectorMobile::getUrl(),
            'collector.visits' => CollectorVisitsReport::getUrl(),
            'coupons.index' => CouponResource::getUrl(),
            'promotional_offers.index' => PromotionalOfferResource::getUrl(),
            default => '#',
        };
    }
}
