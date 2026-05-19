<?php

namespace App\Support;

use App\Filament\Pages\ManageOpticalLaserSettings;
use App\Filament\Pages\NetworkTopology;
use App\Filament\Pages\OltMacTable;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\SnmpMonitor;
use App\Filament\Resources\OltResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class OltSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'mac_table',
                'label' => 'OLT MAC Table',
                'icon' => 'heroicon-o-table-cells',
                'sort' => 1,
                'url' => OltMacTable::getUrl(),
                'active_routes' => ['filament.admin.pages.olt-mac-table'],
            ],
            [
                'key' => 'olt_manage',
                'label' => 'OLT manage',
                'icon' => 'heroicon-o-server-stack',
                'sort' => 2,
                'url' => OltResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.olts.index',
                    'filament.admin.resources.olts.create',
                    'filament.admin.resources.olts.edit',
                ],
            ],
            [
                'key' => 'optical_noc',
                'label' => 'Optical NOC',
                'icon' => 'heroicon-o-light-bulb',
                'sort' => 3,
                'url' => OpticalMonitoringHub::getUrl(),
                'active_routes' => ['filament.admin.pages.optical-noc'],
            ],
            [
                'key' => 'laser_thresholds',
                'label' => 'Laser thresholds',
                'icon' => 'heroicon-o-adjustments-vertical',
                'sort' => 4,
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
            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('OLT & Tools')
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
