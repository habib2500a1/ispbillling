<?php

namespace App\Support;

use App\Filament\Pages\GenerateBandwidthInvoice;
use App\Filament\Resources\BandwidthClientInvoiceResource;
use App\Filament\Resources\BandwidthClientPaymentResource;
use App\Filament\Resources\BandwidthClientResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class BwSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'clients',
                'label' => 'Bandwidth Clients',
                'icon' => 'heroicon-o-signal',
                'sort' => 1,
                'url' => BandwidthClientResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.bandwidth-clients.index',
                    'filament.admin.resources.bandwidth-clients.edit',
                ],
            ],
            [
                'key' => 'invoices',
                'label' => 'Bandwidth Invoices',
                'icon' => 'heroicon-o-document-text',
                'sort' => 2,
                'url' => BandwidthClientInvoiceResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.bandwidth-client-invoices.index',
                    'filament.admin.resources.bandwidth-client-invoices.edit',
                ],
            ],
            [
                'key' => 'generate',
                'label' => 'Generate BW Invoice',
                'icon' => 'heroicon-o-document-plus',
                'sort' => 3,
                'url' => GenerateBandwidthInvoice::getUrl(),
                'active_routes' => ['filament.admin.pages.generate-bandwidth-invoice'],
            ],
            [
                'key' => 'payments',
                'label' => 'Bandwidth Payments',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 4,
                'url' => BandwidthClientPaymentResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.bandwidth-client-payments.index',
                    'filament.admin.resources.bandwidth-client-payments.create',
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
            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('BW Client')
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
}
