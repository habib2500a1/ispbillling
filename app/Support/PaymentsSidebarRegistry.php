<?php

namespace App\Support;

use App\Filament\Pages\ManagePaymentSettings;
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
                'key' => 'pending_gateway',
                'label' => 'Pending gateway',
                'icon' => 'heroicon-o-clock',
                'sort' => 3,
                'url' => PendingGatewayPaymentResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.pending-gateway-payments.index',
                    'filament.admin.resources.pending-gateway-payments.edit',
                ],
            ],
            [
                'key' => 'gateway_settings',
                'label' => 'Gateway settings',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort' => 4,
                'url' => ManagePaymentSettings::getUrl(),
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
            'all', 'record' => PaymentResource::canViewAny(),
            'pending_gateway' => PendingGatewayPaymentResource::canViewAny(),
            'gateway_settings' => ManagePaymentSettings::canAccess(),
            default => false,
        };
    }
}
