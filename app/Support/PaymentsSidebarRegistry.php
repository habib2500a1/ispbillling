<?php

namespace App\Support;

use App\Filament\Pages\ManageMfsSmsSettings;
use App\Filament\Pages\ManagePaymentSettings;
use App\Filament\Pages\ManagePersonalMfsSettings;
use App\Filament\Resources\MfsSmsRecordResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PendingGatewayPaymentResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class PaymentsSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'all',
                'label' => 'All payments',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 1,
                'url' => PaymentResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.payments.index',
                    'filament.admin.resources.payments.edit',
                    'filament.admin.resources.payments.view',
                ],
            ],
            [
                'key' => 'record',
                'label' => 'Record payment',
                'icon' => 'heroicon-o-plus-circle',
                'sort' => 2,
                'url' => PaymentResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.payments.create'],
            ],
            [
                'key' => 'personal_mfs',
                'label' => 'Personal bKash / Nagad',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 10,
                'url' => ManagePersonalMfsSettings::getUrl(['tab' => 'bkash']),
                'active_routes' => ['filament.admin.pages.personal-mfs-verify'],
            ],
            [
                'key' => 'mfs_sms_apps',
                'label' => 'RCL SMS & apps',
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'sort' => 11,
                'url' => ManageMfsSmsSettings::getUrl(),
                'active_routes' => ['filament.admin.pages.mfs-sms-verify'],
            ],
            [
                'key' => 'mfs_sms',
                'label' => 'RCL SMS ledger',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'sort' => 13,
                'url' => MfsSmsRecordResource::getUrl(),
                'active_routes' => ['filament.admin.resources.mfs-sms-records.index'],
            ],
            [
                'key' => 'pending_gateway',
                'label' => 'Pending verify',
                'icon' => 'heroicon-o-clock',
                'sort' => 14,
                'url' => PendingGatewayPaymentResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.pending-gateway-payments.index',
                    'filament.admin.resources.pending-gateway-payments.edit',
                ],
            ],
            [
                'key' => 'merchant_gateways',
                'label' => 'Merchant gateways',
                'icon' => 'heroicon-o-building-storefront',
                'sort' => 20,
                'url' => ManagePaymentSettings::getUrl(['gateway' => 'piprapay', 'merchant' => '1']),
                'active_routes' => ['filament.admin.pages.payment-gateway-settings'],
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
                ->group('Payments')
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    if ($entry['key'] === 'personal_mfs') {
                        return request()->routeIs('filament.admin.pages.personal-mfs-verify');
                    }
                    if ($entry['key'] === 'merchant_gateways') {
                        return request()->routeIs('filament.admin.pages.payment-gateway-settings');
                    }

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
            'all', 'record' => PaymentResource::canViewAny(),
            'pending_gateway' => PendingGatewayPaymentResource::canViewAny(),
            'mfs_sms' => MfsSmsRecordResource::canViewAny(),
            'personal_mfs' => ManagePersonalMfsSettings::canAccess(),
            'mfs_sms_apps' => ManageMfsSmsSettings::canAccess(),
            'merchant_gateways' => ManagePaymentSettings::canAccess(),
            default => false,
        };
    }
}
