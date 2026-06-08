<?php

namespace App\Filament\Resources\MikrotikServerResource\Pages\Concerns;

use App\Filament\Pages\BandwidthMonitor;
use App\Filament\Pages\ImportFromMikrotikPage;
use App\Filament\Pages\MikrotikDashboard;
use App\Filament\Pages\NetworkIntelligenceHub;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Resources\MikrotikServerResource;
use App\Models\MikrotikServer;

/**
 * Premium router list chrome (UI only — preserves Filament table/actions).
 */
trait UsesNetworkRouterLayout
{
    /**
     * @return array{total: int, enabled: int, online: int, offline: int, warning: int, subscribers: int}
     */
    public function getNetworkRouterStats(): array
    {
        $base = MikrotikServer::query();
        $total = (int) (clone $base)->count();
        $online = (int) (clone $base)->where('last_api_status', 'online')->count();
        $offline = (int) (clone $base)->where('last_api_status', 'offline')->count();

        return [
            'total' => $total,
            'enabled' => (int) (clone $base)->where('is_enabled', true)->count(),
            'online' => $online,
            'offline' => $offline,
            'warning' => max(0, $total - $online - $offline),
            'subscribers' => (int) MikrotikServer::query()->withCount('customers')->get()->sum('customers_count'),
        ];
    }

    /**
     * @return array{total: int, enabled: int, online: int, offline: int, warning: int, subscribers: int}
     */
    public function getNetworkFleetStats(): array
    {
        return $this->getNetworkRouterStats();
    }

    /**
     * @return list<array{label: string, value: string, hint?: string, tone: string, url?: string}>
     */
    public function getNetworkStatCards(): array
    {
        $s = $this->getNetworkRouterStats();

        return [
            [
                'label' => 'Total routers',
                'value' => number_format($s['total']),
                'tone' => 'indigo',
            ],
            [
                'label' => 'Online',
                'value' => number_format($s['online']),
                'tone' => 'emerald',
                'url' => MikrotikServerResource::getUrl('index'),
            ],
            [
                'label' => 'Offline',
                'value' => number_format($s['offline']),
                'tone' => 'rose',
            ],
            [
                'label' => 'Subscribers',
                'value' => number_format($s['subscribers']),
                'hint' => number_format($s['enabled']).' enabled',
                'tone' => 'cyan',
                'url' => OnlineClientsMonitoring::getUrl(),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function getNetworkFilterChips(): array
    {
        $chips = [];
        $enabled = data_get($this->tableFilters, 'is_enabled.value');

        if ($enabled === true || $enabled === '1' || $enabled === 1) {
            $chips[] = ['key' => 'enabled', 'label' => 'Enabled only'];
        } elseif ($enabled === false || $enabled === '0' || $enabled === 0) {
            $chips[] = ['key' => 'disabled', 'label' => 'Disabled only'];
        }

        if (filled($this->tableSearch)) {
            $chips[] = ['key' => 'search', 'label' => 'Search: '.$this->tableSearch];
        }

        return $chips;
    }

    public function getNetworkActiveFilterCount(): int
    {
        return count($this->getNetworkFilterChips());
    }

    public function getNetworkResultSummary(): string
    {
        return number_format($this->getTableRecords()->total()).' routers';
    }

    public function resetNetworkToolbar(): void
    {
        $this->tableSearch = '';
        $this->tableFilters = [];
        $this->resetPage();
    }

    public function getNetworkFilterChipUrl(string $key): string
    {
        $params = [];

        if ($key !== 'search' && filled($this->tableSearch)) {
            $params['tableSearch'] = $this->tableSearch;
        }

        $filters = $this->tableFilters ?? [];

        if ($key === 'enabled' || $key === 'disabled') {
            unset($filters['is_enabled']);
        }

        if ($filters !== []) {
            $params['tableFilters'] = $filters;
        }

        return MikrotikServerResource::getUrl('index', $params);
    }

    /**
     * @return list<array{url: string, label: string, icon: string, active?: bool}>
     */
    public function getNetworkDockLinks(): array
    {
        return [
            ['url' => NetworkIntelligenceHub::getUrl(), 'label' => 'Center', 'icon' => 'heroicon-o-squares-2x2'],
            ['url' => MikrotikServerResource::getUrl('index'), 'label' => 'Routers', 'icon' => 'heroicon-o-server', 'active' => true],
            ['url' => OnlineClientsMonitoring::getUrl(), 'label' => 'Live', 'icon' => 'heroicon-o-bolt'],
            ['url' => BandwidthMonitor::getUrl(), 'label' => 'Traffic', 'icon' => 'heroicon-o-chart-bar'],
            ['url' => MikrotikServerResource::getUrl('create'), 'label' => 'Add', 'icon' => 'heroicon-o-plus'],
        ];
    }

    /**
     * @return list<array{id: int, name: string, host: string, port: string, status: string, subscribers: int, enabled: bool, edit_url: string}>
     */
    public function getNetworkRouterCards(): array
    {
        return $this->getTableRecords()->map(function (MikrotikServer $router): array {
            $status = (string) ($router->last_api_status ?: 'unknown');

            return [
                'id' => (int) $router->getKey(),
                'name' => (string) $router->name,
                'host' => (string) $router->host,
                'port' => ($router->use_ssl ? 'ssl://' : '').$router->host.':'.$router->api_port,
                'status' => $status,
                'subscribers' => (int) ($router->customers_count ?? $router->customers()->count()),
                'enabled' => (bool) $router->is_enabled,
                'edit_url' => MikrotikServerResource::getUrl('edit', ['record' => $router]),
            ];
        })->all();
    }

    /**
     * @return list<array{url: string, label: string, icon: string}>
     */
    public function getNetworkQuickLinks(): array
    {
        return [
            ['url' => NetworkIntelligenceHub::getUrl(), 'label' => 'Network center', 'icon' => 'heroicon-o-cpu-chip'],
            ['url' => OnlineClientsMonitoring::getUrl(), 'label' => 'Live PPP', 'icon' => 'heroicon-o-bolt'],
            ['url' => ImportFromMikrotikPage::getUrl(), 'label' => 'Import PPP', 'icon' => 'heroicon-o-arrow-down-tray'],
            ['url' => MikrotikDashboard::getUrl(), 'label' => 'Dashboard', 'icon' => 'heroicon-o-chart-pie'],
        ];
    }
}
