<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Pages\Concerns\PresentsOltOperationsKpis;
use App\Filament\Resources\OltResource;
use App\Models\Device;
use App\Services\Olt\GponNocHubService;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Filament\Pages\Page;

class OltHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;
    use PresentsOltOperationsKpis;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static string $view = 'filament.pages.olt-hub';

    protected static ?string $slug = 'olt-hub';

    protected static ?string $title = '';

    public function getTitle(): string
    {
        return '';
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-olt-module',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->cachedHubStats(function (): array {
            try {
                return app(GponNocHubService::class)->snapshot(TenantResolver::requiredTenantId());
            } catch (\Throwable) {
                $onus = Device::query()->where('type', 'onu');

                return [
                    'olts' => Device::query()->where('type', 'olt')->count(),
                    'pon_ports' => 0,
                    'onus' => (clone $onus)->count(),
                    'onus_online' => (clone $onus)->whereIn('onu_oper_status', ['online', 'active', 'up'])->count(),
                    'onus_offline' => 0,
                    'active_alarms' => 0,
                    'fiber_faults' => 0,
                    'critical_onus' => 0,
                    'warning_onus' => 0,
                    'avg_rx_dbm' => null,
                    'top_pon_ports' => [],
                    'vendors' => [],
                ];
            }
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
                'value' => number_format($s['olts'] ?? 0),
                'hint' => number_format($s['olts_active'] ?? 0).' active chassis',
                'url' => OltResource::getUrl(),
                'tone' => 'cyan',
                'icon' => 'heroicon-o-server-stack',
            ],
            [
                'label' => 'PON ports',
                'value' => number_format($s['pon_ports'] ?? 0),
                'hint' => 'Registered PON interfaces',
                'url' => OpticalMonitoringHub::getUrl(),
                'tone' => 'indigo',
                'icon' => 'heroicon-o-circle-stack',
            ],
            [
                'label' => 'ONUs',
                'value' => number_format($s['onus'] ?? 0),
                'hint' => $onlinePct.'% online · '.number_format($s['onus_offline'] ?? 0).' offline',
                'url' => OpticalMonitoringHub::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-cpu-chip',
            ],
            [
                'label' => 'Active alarms',
                'value' => number_format($s['active_alarms'] ?? 0),
                'hint' => number_format($s['fiber_faults'] ?? 0).' fiber faults open',
                'url' => OpticalMonitoringHub::getUrl().'?tab=alerts',
                'tone' => ($s['active_alarms'] ?? 0) > 0 ? 'rose' : 'emerald',
                'icon' => 'heroicon-o-bell-alert',
            ],
            [
                'label' => 'Weak signal',
                'value' => number_format(($s['critical_onus'] ?? 0) + ($s['warning_onus'] ?? 0)),
                'hint' => $s['avg_rx_dbm'] !== null ? 'Avg RX '.number_format((float) $s['avg_rx_dbm'], 1).' dBm' : 'Poll OLT for RX',
                'url' => OpticalMonitoringHub::getUrl(),
                'tone' => 'amber',
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
                'desc' => 'Huawei · ZTE · BDCOM · VSOL · Fiberhome — SNMP sync, CPU/RAM/temp health, ONU inventory.',
                'url' => OltResource::getUrl(),
                'icon' => 'heroicon-o-list-bullet',
                'tone' => 'cyan',
                'featured' => true,
            ],
            [
                'title' => 'OLT VPN / PPTP',
                'desc' => 'Private IP reach — PPTP, OpenVPN (.ovpn), test Direct → OpenVPN → PPTP.',
                'url' => OltVpnManagementPage::getUrl(),
                'icon' => 'heroicon-o-shield-check',
                'tone' => 'sky',
            ],
            [
                'title' => 'Optical NOC',
                'desc' => 'RX/TX dBm, temp/voltage, PON stats, topology, optical power heatmap, alerts.',
                'url' => OpticalMonitoringHub::getUrl(),
                'icon' => 'heroicon-o-light-bulb',
                'tone' => 'violet',
            ],
            [
                'title' => 'Topology map',
                'desc' => 'MikroTik → OLT → PON → ONU tree — multi-vendor single pane.',
                'url' => NetworkTopology::getUrl(),
                'icon' => 'heroicon-o-share',
                'tone' => 'indigo',
            ],
            [
                'title' => 'PON MAC table',
                'desc' => 'FDB / MAC inventory from OLT SNMP — customer mapping.',
                'url' => OltMacTable::getUrl(),
                'icon' => 'heroicon-o-table-cells',
                'tone' => 'emerald',
            ],
            [
                'title' => 'Laser thresholds',
                'desc' => 'Low RX, high laser, ONU temperature — Telegram/WhatsApp/SMS alerts.',
                'url' => ManageOpticalLaserSettings::getUrl(),
                'icon' => 'heroicon-o-adjustments-vertical',
                'tone' => 'amber',
            ],
            [
                'title' => 'Fiber plant map',
                'desc' => 'GIS-style fiber routes and splice points (when configured).',
                'url' => FiberPlantMap::getUrl(),
                'icon' => 'heroicon-o-map',
                'tone' => 'sky',
            ],
        ];

        if (NetworkIntelligenceHub::canAccess()) {
            $cards[] = [
                'title' => 'SNMP & NetFlow',
                'desc' => 'Poll logs, uplink traffic, interface status, FEC/errors.',
                'url' => NetworkIntelligenceHub::getUrl(),
                'icon' => 'heroicon-o-chart-bar',
                'tone' => 'slate',
            ];
        }

        return $cards;
    }

    /**
     * @return list<string>
     */
    public function getSupportedVendorLabels(): array
    {
        return collect(config('olt_drivers.drivers', []))
            ->pluck('label')
            ->values()
            ->all();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && StaffCapability::for($user)->canOlt();
    }
}
