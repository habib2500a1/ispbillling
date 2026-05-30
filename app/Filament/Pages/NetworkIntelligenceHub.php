<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\AreaResource;
use App\Filament\Resources\HotspotVoucherResource;
use App\Filament\Resources\IpPoolResource;
use App\Filament\Resources\MikrotikServerResource;
use App\Filament\Resources\PopBoxResource;
use App\Models\Device;
use App\Models\MikrotikServer;
use App\Models\NetflowFlow;
use App\Models\SnmpPollLog;
use App\Support\Rbac\StaffCapability;
use App\Support\SnmpClient;
use Filament\Pages\Page;

class NetworkIntelligenceHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string $view = 'filament.pages.network-intelligence-hub';

    protected static ?string $navigationLabel = 'Network center';

    protected static ?string $title = '';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 0;

    public function getTitle(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return $this->cachedHubStats(function (): array {
            $olts = Device::query()->olts()->count();
            $onus = Device::query()->where('type', 'onu')->count();
            $onusOnline = Device::query()->where('type', 'onu')->whereIn('onu_oper_status', ['online', 'active', 'up'])->count();
            $lastPoll = SnmpPollLog::query()->orderByDesc('polled_at')->first();
            $flows24h = NetflowFlow::query()->where('sampled_at', '>=', now()->subDay());
            $mikrotik = MikrotikServer::query()->count();

            return [
                'olts' => $olts,
                'onus' => $onus,
                'onus_online' => $onusOnline,
                'onus_offline' => max(0, $onus - $onusOnline),
                'mikrotik' => $mikrotik,
                'snmp_available' => SnmpClient::available(),
                'last_poll' => $lastPoll?->polled_at?->diffForHumans(),
                'last_poll_ok' => $lastPoll?->success,
                'flows_24h' => (clone $flows24h)->count(),
                'flow_bytes_24h' => (int) (clone $flows24h)->sum('bytes'),
                'netflow_enabled' => (bool) config('netflow.enabled'),
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
                'label' => 'MikroTik',
                'value' => number_format($s['mikrotik']),
                'hint' => 'Routers & PPPoE sync',
                'url' => MikrotikServerResource::getUrl(),
                'tone' => 'cyan',
                'icon' => 'heroicon-o-server',
            ],
            [
                'label' => 'OLTs',
                'value' => number_format($s['olts']),
                'hint' => 'GPON line terminals',
                'url' => OltHub::canAccess() ? OltHub::getUrl() : SnmpMonitor::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-server-stack',
            ],
            [
                'label' => 'ONUs online',
                'value' => number_format($s['onus_online']),
                'hint' => $onlinePct.'% up · '.$s['onus_offline'].' offline',
                'url' => OpticalMonitoringHub::canAccess() ? OpticalMonitoringHub::getUrl() : SnmpMonitor::getUrl(),
                'tone' => ($s['onus_offline'] ?? 0) > 0 ? 'amber' : 'emerald',
                'icon' => 'heroicon-o-signal',
            ],
            [
                'label' => 'NetFlow (24h)',
                'value' => number_format($s['flows_24h']),
                'hint' => $this->formatBytes($s['flow_bytes_24h']).' sampled',
                'url' => NetflowAnalysis::getUrl(),
                'tone' => 'sky',
                'icon' => 'heroicon-o-arrows-right-left',
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool}>
     */
    public function getActionCards(): array
    {
        return [
            [
                'title' => 'Online clients',
                'desc' => 'Live PPPoE sessions — router sync, uptime, IP, and disconnect actions.',
                'url' => OnlineClientsMonitoring::getUrl(),
                'icon' => 'heroicon-o-bolt',
                'tone' => 'cyan',
                'featured' => true,
            ],
            [
                'title' => 'MikroTik servers',
                'desc' => 'Router inventory, API credentials, PPPoE sync, and health probes.',
                'url' => MikrotikServerResource::getUrl(),
                'icon' => 'heroicon-o-server',
                'tone' => 'indigo',
            ],
            [
                'title' => 'SNMP monitor',
                'desc' => 'Poll logs, interface status, OLT metrics, and failure history.',
                'url' => SnmpMonitor::getUrl(),
                'icon' => 'heroicon-o-signal',
                'tone' => 'emerald',
            ],
            [
                'title' => 'NetFlow analysis',
                'desc' => 'Top talkers, protocols, exporters, and 24h traffic breakdown.',
                'url' => NetflowAnalysis::getUrl(),
                'icon' => 'heroicon-o-chart-bar',
                'tone' => 'violet',
            ],
            [
                'title' => 'Bandwidth monitor',
                'desc' => 'Usage trends, abuse alerts, and per-subscriber throughput.',
                'url' => BandwidthMonitor::getUrl(),
                'icon' => 'heroicon-o-chart-pie',
                'tone' => 'amber',
            ],
            [
                'title' => 'RADIUS users',
                'desc' => 'radcheck / radusergroup admin — credentials and group bindings.',
                'url' => RadiusUserAdmin::getUrl(),
                'icon' => 'heroicon-o-circle-stack',
                'tone' => 'slate',
            ],
            [
                'title' => 'Network topology',
                'desc' => 'MikroTik → OLT → PON → ONU tree map and plant visualization.',
                'url' => NetworkTopology::getUrl(),
                'icon' => 'heroicon-o-share',
                'tone' => 'indigo',
            ],
            [
                'title' => 'Fiber plant map',
                'desc' => 'Splitter, cable color, meter distance — field plant GIS map.',
                'url' => FiberPlantMap::getUrl(),
                'icon' => 'heroicon-o-map',
                'tone' => 'teal',
            ],
            [
                'title' => 'IP pools',
                'desc' => 'Static IP allocation, CIDR blocks, and assignment tracking.',
                'url' => IpPoolResource::getUrl(),
                'icon' => 'heroicon-o-globe-alt',
                'tone' => 'sky',
            ],
            [
                'title' => 'POP / boxes',
                'desc' => 'Site inventory, cabinet capacity, and field locations.',
                'url' => PopBoxResource::getUrl(),
                'icon' => 'heroicon-o-building-office-2',
                'tone' => 'emerald',
            ],
            [
                'title' => 'Hotspot vouchers',
                'desc' => 'Prepaid Wi‑Fi cards and captive portal voucher sales.',
                'url' => HotspotVoucherResource::getUrl(),
                'icon' => 'heroicon-o-wifi',
                'tone' => 'amber',
            ],
            [
                'title' => 'Coverage areas',
                'desc' => 'Area → zone → subzone mapping for network coverage.',
                'url' => AreaResource::getUrl(),
                'icon' => 'heroicon-o-map-pin',
                'tone' => 'slate',
            ],
        ];
    }

    /**
     * @return list<array{command: string, desc: string, tag: string, tone: string}>
     */
    public function getAutomationItems(): array
    {
        return [
            [
                'command' => 'isp:poll-olt-intelligence',
                'desc' => 'SNMP poll all OLTs every 10 minutes — optical & interface metrics.',
                'tag' => 'Scheduled',
                'tone' => 'emerald',
            ],
            [
                'command' => 'isp:process-netflow-inbox',
                'desc' => 'Import JSON flow files from storage/app/netflow/inbox/.',
                'tag' => 'Ingest',
                'tone' => 'cyan',
            ],
            [
                'command' => 'POST /api/webhooks/netflow-ingest',
                'desc' => 'Push flows via webhook with X-Netflow-Secret header.',
                'tag' => 'Webhook',
                'tone' => 'violet',
            ],
            [
                'command' => 'isp:sync-onu-status-from-meta',
                'desc' => 'Sync ONU optical/status from stored devices.meta keys.',
                'tag' => 'Sync',
                'tone' => 'amber',
            ],
        ];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return round($bytes / 1_073_741_824, 1).' GB';
        }

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canNetwork();
    }
}
