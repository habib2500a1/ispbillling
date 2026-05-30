<?php

namespace App\Support;

use App\Filament\Pages\BandwidthMonitor;
use App\Filament\Pages\ManageNetworkSettings;
use App\Filament\Pages\FiberPlantMap;
use App\Filament\Pages\ImportFromMikrotikPage;
use App\Filament\Pages\NetflowAnalysis;
use App\Filament\Pages\NetworkIntelligenceHub;
use App\Filament\Pages\NetworkTopology;
use App\Filament\Pages\RadiusUserAdmin;
use App\Filament\Pages\SnmpMonitor;
use App\Filament\Pages\SubscriberTrafficMonitor;
use App\Filament\Resources\AreaResource;
use App\Filament\Resources\HotspotVoucherResource;
use App\Filament\Resources\IpPoolResource;
use App\Filament\Resources\MikrotikServerResource;
use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PopBoxResource;
use App\Filament\Resources\SubzoneResource;
use App\Filament\Resources\ZoneResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class NetworkSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'network_center',
                'label' => 'Network center',
                'icon' => 'heroicon-o-cpu-chip',
                'sort' => -1,
                'url' => NetworkIntelligenceHub::getUrl(),
                'active_routes' => ['filament.admin.pages.network-intelligence-hub'],
            ],
            [
                'key' => 'network_setup',
                'label' => 'API & RADIUS setup',
                'icon' => 'heroicon-o-adjustments-horizontal',
                'sort' => 0,
                'url' => ManageNetworkSettings::getUrl(),
                'active_routes' => ['filament.admin.pages.network-settings'],
            ],
            [
                'key' => 'routers_list',
                'label' => 'Routers list',
                'icon' => 'heroicon-o-server-stack',
                'sort' => 1,
                'url' => MikrotikServerResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.mikrotik-servers.index',
                    'filament.admin.resources.mikrotik-servers.edit',
                ],
            ],
            [
                'key' => 'add_router',
                'label' => 'Add router',
                'icon' => 'heroicon-o-plus-circle',
                'sort' => 2,
                'url' => MikrotikServerResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.mikrotik-servers.create'],
            ],
            [
                'key' => 'areas',
                'label' => 'Area',
                'icon' => 'heroicon-o-map-pin',
                'sort' => 3,
                'url' => AreaResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.areas.index',
                    'filament.admin.resources.areas.create',
                    'filament.admin.resources.areas.edit',
                ],
            ],
            [
                'key' => 'zones',
                'label' => 'Zone',
                'icon' => 'heroicon-o-map',
                'sort' => 4,
                'url' => ZoneResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.zones.index',
                    'filament.admin.resources.zones.create',
                    'filament.admin.resources.zones.edit',
                ],
            ],
            [
                'key' => 'subzones',
                'label' => 'Subzone',
                'icon' => 'heroicon-o-squares-2x2',
                'sort' => 5,
                'url' => SubzoneResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.subzones.index',
                    'filament.admin.resources.subzones.create',
                    'filament.admin.resources.subzones.edit',
                ],
            ],
            [
                'key' => 'packages',
                'label' => 'Packages',
                'icon' => 'heroicon-o-cube',
                'sort' => 6,
                'url' => PackageResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.packages.index',
                    'filament.admin.resources.packages.create',
                    'filament.admin.resources.packages.edit',
                ],
            ],
            [
                'key' => 'import_mikrotik',
                'label' => 'Import from MikroTik',
                'icon' => 'heroicon-o-arrow-down-tray',
                'sort' => 7,
                'url' => ImportFromMikrotikPage::getUrl(),
                'active_routes' => ['filament.admin.pages.import-from-mikrotik'],
            ],
            [
                'key' => 'bandwidth',
                'label' => 'Bandwidth monitor',
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 10,
                'url' => BandwidthMonitor::getUrl(),
                'active_routes' => ['filament.admin.pages.bandwidth-monitor'],
            ],
            [
                'key' => 'topology',
                'label' => 'Topology map',
                'icon' => 'heroicon-o-share',
                'sort' => 12,
                'url' => NetworkTopology::getUrl(),
                'active_routes' => ['filament.admin.pages.network-topology'],
            ],
            [
                'key' => 'fiber_map',
                'label' => 'Fiber plant map',
                'icon' => 'heroicon-o-map',
                'sort' => 11,
                'url' => FiberPlantMap::getUrl(),
                'active_routes' => ['filament.admin.pages.fiber-plant-map'],
            ],
            [
                'key' => 'radius',
                'label' => 'RADIUS users',
                'icon' => 'heroicon-o-circle-stack',
                'sort' => 13,
                'url' => RadiusUserAdmin::getUrl(),
                'active_routes' => ['filament.admin.pages.radius-user-admin'],
            ],
            [
                'key' => 'traffic',
                'label' => 'Subscriber traffic',
                'icon' => 'heroicon-o-arrow-trending-up',
                'sort' => 14,
                'url' => SubscriberTrafficMonitor::getUrl(),
                'active_routes' => ['filament.admin.pages.subscriber-traffic-monitor'],
            ],
            [
                'key' => 'snmp',
                'label' => 'SNMP monitor',
                'icon' => 'heroicon-o-bolt',
                'sort' => 15,
                'url' => SnmpMonitor::getUrl(),
                'active_routes' => ['filament.admin.pages.snmp-monitor'],
            ],
            [
                'key' => 'netflow',
                'label' => 'NetFlow analysis',
                'icon' => 'heroicon-o-arrows-right-left',
                'sort' => 16,
                'url' => NetflowAnalysis::getUrl(),
                'active_routes' => ['filament.admin.pages.netflow-analysis'],
            ],
            [
                'key' => 'ip_pools',
                'label' => 'IP pools',
                'icon' => 'heroicon-o-globe-alt',
                'sort' => 17,
                'url' => IpPoolResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.ip-pools.index',
                    'filament.admin.resources.ip-pools.create',
                    'filament.admin.resources.ip-pools.edit',
                ],
            ],
            [
                'key' => 'pop',
                'label' => 'POP / boxes',
                'icon' => 'heroicon-o-building-office-2',
                'sort' => 18,
                'url' => PopBoxResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.pop-boxes.index',
                    'filament.admin.resources.pop-boxes.create',
                    'filament.admin.resources.pop-boxes.edit',
                ],
            ],
            [
                'key' => 'hotspot',
                'label' => 'Hotspot vouchers',
                'icon' => 'heroicon-o-ticket',
                'sort' => 19,
                'url' => HotspotVoucherResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.hotspot-vouchers.index',
                    'filament.admin.resources.hotspot-vouchers.create',
                    'filament.admin.resources.hotspot-vouchers.edit',
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
                ->group('Network')
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
            'network_center' => NetworkIntelligenceHub::canAccess(),
            'network_setup' => ManageNetworkSettings::canAccess(),
            'routers_list', 'add_router' => MikrotikServerResource::canViewAny(),
            'import_mikrotik' => ImportFromMikrotikPage::canAccess(),
            'areas' => AreaResource::canViewAny(),
            'zones' => ZoneResource::canViewAny(),
            'subzones' => SubzoneResource::canViewAny(),
            'packages' => PackageResource::canViewAny(),
            'bandwidth' => BandwidthMonitor::canAccess(),
            'topology' => NetworkTopology::canAccess(),
            'fiber_map' => FiberPlantMap::canAccess(),
            'radius' => RadiusUserAdmin::canAccess(),
            'traffic' => SubscriberTrafficMonitor::canAccess(),
            'snmp' => SnmpMonitor::canAccess(),
            'netflow' => NetflowAnalysis::canAccess(),
            'ip_pools' => IpPoolResource::canViewAny(),
            'pop' => PopBoxResource::canViewAny(),
            'hotspot' => HotspotVoucherResource::canViewAny(),
            default => false,
        };
    }
}
