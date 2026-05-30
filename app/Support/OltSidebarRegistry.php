<?php

namespace App\Support;

use App\Filament\Pages\FiberPlantMap;
use App\Filament\Pages\ManageOpticalLaserSettings;
use App\Filament\Pages\OltHub;
use App\Filament\Pages\OltMacTable;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\NetworkTopology;
use App\Filament\Resources\OltResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class OltSidebarRegistry
{
    /** Filament sidebar group — replaces legacy «Inventory Pro» slot (Payments → OLT & Tools). */
    public const GROUP_LABEL = 'OLT & Tools';

    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'hub',
                'label' => 'OLT',
                'icon' => 'heroicon-o-server-stack',
                'sort' => 0,
                'url' => OltHub::getUrl(),
                'active_routes' => ['filament.admin.pages.olt-hub'],
            ],
            [
                'key' => 'olt_manage',
                'label' => 'OLT list',
                'icon' => 'heroicon-o-list-bullet',
                'sort' => 1,
                'url' => OltResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.olts.index',
                    'filament.admin.resources.olts.create',
                    'filament.admin.resources.olts.edit',
                ],
            ],
            [
                'key' => 'optical_noc',
                'label' => 'Optical Database',
                'icon' => 'heroicon-o-light-bulb',
                'sort' => 2,
                'url' => OpticalMonitoringHub::getUrl(),
                'active_routes' => ['filament.admin.pages.optical-noc'],
            ],
            [
                'key' => 'topology',
                'label' => 'Topology',
                'icon' => 'heroicon-o-share',
                'sort' => 3,
                'url' => NetworkTopology::getUrl(),
                'active_routes' => ['filament.admin.pages.network-topology'],
            ],
            [
                'key' => 'fiber_map',
                'label' => 'Fiber map',
                'icon' => 'heroicon-o-map',
                'sort' => 4,
                'url' => FiberPlantMap::getUrl(),
                'active_routes' => ['filament.admin.pages.fiber-plant-map'],
            ],
            [
                'key' => 'mac_table',
                'label' => 'MAC table',
                'icon' => 'heroicon-o-table-cells',
                'sort' => 5,
                'url' => OltMacTable::getUrl(),
                'active_routes' => ['filament.admin.pages.olt-mac-table'],
            ],
            [
                'key' => 'laser_thresholds',
                'label' => 'Laser thresholds',
                'icon' => 'heroicon-o-adjustments-vertical',
                'sort' => 6,
                'url' => ManageOpticalLaserSettings::getUrl(),
                'active_routes' => ['filament.admin.pages.optical-laser-settings'],
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
                ->group(self::GROUP_LABEL)
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
            'hub' => OltHub::canAccess(),
            'mac_table' => OltMacTable::canAccess(),
            'olt_manage' => OltResource::canViewAny(),
            'optical_noc' => OpticalMonitoringHub::canAccess(),
            'topology' => NetworkTopology::canAccess(),
            'fiber_map' => FiberPlantMap::canAccess(),
            'laser_thresholds' => ManageOpticalLaserSettings::canAccess(),
            default => false,
        };
    }
}
