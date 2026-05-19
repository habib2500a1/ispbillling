<?php

namespace App\Support;

use App\Filament\Pages\AreaWiseClientsReport;
use App\Filament\Pages\ClientsHub;
use App\Filament\Pages\ExportClientsReport;
use App\Filament\Pages\ImportClientsCsvPage;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Resources\CustomerResource;
use App\Services\Billing\BillingAccountListCounts;
use App\Services\Clients\ClientsDashboardService;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class ClientsSidebarRegistry
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   url: string,
     *   active_routes: list<string>,
     *   badge_key?: string,
     * }>
     */
    public static function definitions(): array
    {
        $index = CustomerResource::getUrl('index');

        return [
            [
                'key' => 'hub',
                'label' => 'Clients overview',
                'icon' => 'heroicon-o-squares-2x2',
                'sort' => 0,
                'url' => ClientsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.clients-hub'],
            ],
            [
                'key' => 'add',
                'label' => 'Add client',
                'icon' => 'heroicon-o-user-plus',
                'sort' => 1,
                'url' => CustomerResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.subscribers.create'],
            ],
            [
                'key' => 'all',
                'label' => 'All clients',
                'icon' => 'heroicon-o-users',
                'sort' => 2,
                'url' => $index,
                'active_routes' => [
                    'filament.admin.resources.subscribers.index',
                    'filament.admin.resources.subscribers.view',
                    'filament.admin.resources.subscribers.edit',
                ],
                'badge_key' => 'total',
            ],
            [
                'key' => 'online',
                'label' => 'Online clients',
                'icon' => 'heroicon-o-signal',
                'sort' => 3,
                'url' => $index.'?preset=online',
                'active_routes' => [],
                'badge_key' => 'online',
            ],
            [
                'key' => 'active',
                'label' => 'Active clients',
                'icon' => 'heroicon-o-check-circle',
                'sort' => 4,
                'url' => CustomerResource::getUrl('active'),
                'active_routes' => ['filament.admin.resources.subscribers.active'],
                'badge_key' => 'active',
            ],
            [
                'key' => 'expired',
                'label' => 'Expired clients',
                'icon' => 'heroicon-o-clock',
                'sort' => 5,
                'url' => CustomerResource::getUrl('expired'),
                'active_routes' => ['filament.admin.resources.subscribers.expired'],
                'badge_key' => 'expired',
            ],
            [
                'key' => 'suspended',
                'label' => 'Suspended',
                'icon' => 'heroicon-o-pause-circle',
                'sort' => 6,
                'url' => CustomerResource::getUrl('suspended'),
                'active_routes' => ['filament.admin.resources.subscribers.suspended'],
                'badge_key' => 'suspended',
            ],
            [
                'key' => 'left',
                'label' => 'Left clients',
                'icon' => 'heroicon-o-archive-box',
                'sort' => 7,
                'url' => CustomerResource::getUrl('left'),
                'active_routes' => ['filament.admin.resources.subscribers.left'],
                'badge_key' => 'left',
            ],
            [
                'key' => 'monitor',
                'label' => 'Live PPP monitor',
                'icon' => 'heroicon-o-bolt',
                'sort' => 8,
                'url' => OnlineClientsMonitoring::getUrl(),
                'active_routes' => ['filament.admin.pages.online-clients-monitoring'],
            ],
            [
                'key' => 'import',
                'label' => 'Import CSV',
                'icon' => 'heroicon-o-arrow-up-tray',
                'sort' => 9,
                'url' => ImportClientsCsvPage::getUrl(),
                'active_routes' => ['filament.admin.pages.import-clients-csv'],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function badgeCounts(): array
    {
        try {
            $summary = app(ClientsDashboardService::class)->summary();
            $billing = app(BillingAccountListCounts::class)->all();

            return array_merge($summary, $billing);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null) {
            return [];
        }

        $counts = self::badgeCounts();
        $items = [];

        foreach (self::definitions() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $item = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Clients')
                ->sort($entry['sort']);

            if (isset($entry['badge_key'])) {
                $count = (int) ($counts[$entry['badge_key']] ?? 0);
                if ($count > 0) {
                    $item->badge((string) $count);
                }
            }

            $item->isActiveWhen(function () use ($entry): bool {
                foreach ($entry['active_routes'] as $route) {
                    if (request()->routeIs($route)) {
                        return true;
                    }
                }

                if ($entry['key'] === 'online' && request()->routeIs('filament.admin.resources.subscribers.index')) {
                    return request()->query('preset') === 'online';
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
            'hub' => ClientsHub::canAccess(),
            'add' => CustomerResource::canCreate(),
            'monitor' => OnlineClientsMonitoring::canAccess(),
            'import' => ImportClientsCsvPage::canAccess(),
            default => CustomerResource::canViewAny(),
        };
    }
}
