<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\OltResource;
use App\Models\Device;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class OltHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static string $view = 'filament.pages.olt-hub';

    protected static ?string $slug = 'olt-hub';

    protected static ?string $title = '';

    public function getTitle(): string
    {
        return '';
    }

    /**
     * @return array{olts: int, onus: int, onus_online: int, onus_with_rx: int}
     */
    public function getStats(): array
    {
        return $this->cachedHubStats(function (): array {
            $onus = Device::query()->where('type', 'onu');

            return [
                'olts' => Device::query()->where('type', 'olt')->count(),
                'onus' => (clone $onus)->count(),
                'onus_online' => (clone $onus)->whereIn('onu_oper_status', ['online', 'active', 'up'])->count(),
                'onus_with_rx' => (clone $onus)->whereNotNull('rx_power_dbm')->count(),
            ];
        });
    }

    /**
     * @return list<array{label: string, value: string, hint: string, url: string, tone: string, icon: string}>
     */
    public function getKpiCards(): array
    {
        $s = $this->getStats();
        $onlinePct = ($s['onus'] ?? 0) > 0
            ? round(100 * ($s['onus_online'] ?? 0) / max(1, $s['onus']))
            : 0;

        return [
            [
                'label' => 'OLTs',
                'value' => number_format($s['olts']),
                'hint' => 'Registered chassis',
                'url' => OltResource::getUrl(),
                'tone' => 'cyan',
                'icon' => 'heroicon-o-server-stack',
            ],
            [
                'label' => 'ONUs',
                'value' => number_format($s['onus']),
                'hint' => 'Total CPE rows',
                'url' => OpticalMonitoringHub::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-cpu-chip',
            ],
            [
                'label' => 'Online',
                'value' => number_format($s['onus_online']),
                'hint' => $onlinePct.'% of ONUs up',
                'url' => OpticalMonitoringHub::getUrl(),
                'tone' => 'emerald',
                'icon' => 'heroicon-o-signal',
            ],
            [
                'label' => 'With RX dBm',
                'value' => number_format($s['onus_with_rx']),
                'hint' => 'Optical reading in DB',
                'url' => OpticalMonitoringHub::getUrl(),
                'tone' => 'sky',
                'icon' => 'heroicon-o-light-bulb',
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool}>
     */
    public function getActionCards(): array
    {
        $cards = [
            [
                'title' => 'OLT list',
                'desc' => 'Add Aveis / BDCOM / Huawei — SNMP sync, edit ONUs per PON port.',
                'url' => OltResource::getUrl(),
                'icon' => 'heroicon-o-list-bullet',
                'tone' => 'cyan',
                'featured' => true,
            ],
            [
                'title' => 'Optical Database',
                'desc' => 'RX dBm search — ONU serial, PON, client name, weak signal filter.',
                'url' => OpticalMonitoringHub::getUrl(),
                'icon' => 'heroicon-o-light-bulb',
                'tone' => 'violet',
            ],
            [
                'title' => 'Topology map',
                'desc' => 'MikroTik → OLT → PON → ONU tree visualization.',
                'url' => NetworkTopology::getUrl(),
                'icon' => 'heroicon-o-share',
                'tone' => 'indigo',
            ],
            [
                'title' => 'PON MAC table',
                'desc' => 'MAC inventory polled from OLT SNMP.',
                'url' => OltMacTable::getUrl(),
                'icon' => 'heroicon-o-table-cells',
                'tone' => 'emerald',
            ],
            [
                'title' => 'Laser thresholds',
                'desc' => 'RX/TX dBm bands — weak signal & high laser alerts.',
                'url' => ManageOpticalLaserSettings::getUrl(),
                'icon' => 'heroicon-o-adjustments-vertical',
                'tone' => 'amber',
            ],
        ];

        if (NetworkIntelligenceHub::canAccess()) {
            $cards[] = [
                'title' => 'SNMP & NetFlow',
                'desc' => 'Poll logs, interface status, traffic analysis.',
                'url' => NetworkIntelligenceHub::getUrl(),
                'icon' => 'heroicon-o-chart-bar',
                'tone' => 'slate',
            ];
        }

        return $cards;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && StaffCapability::for($user)->canOlt();
    }
}
