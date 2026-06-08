<?php

namespace App\Filament\Pages;

use App\Services\Dashboard\DashboardMetricsService;
use App\Support\CompanyBranding;
use Filament\Pages\Page;

class NocWall extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static string $view = 'filament.pages.noc-wall';

    protected static ?string $title = 'NOC wall';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.layouts.noc-wall';

    /** @var array<string, mixed> */
    public array $wallData = [];

    public bool $wallReady = false;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        $this->loadWallData();
    }

    public function refreshWallData(): void
    {
        $this->loadWallData();
    }

    public function companyName(): string
    {
        return CompanyBranding::name();
    }

    public function companyLogoUrl(): ?string
    {
        return CompanyBranding::logoUrl();
    }

    public function companyInitial(): string
    {
        return CompanyBranding::brandInitial();
    }

    /**
     * @return array<string, mixed>
     */
    public function getWallData(): array
    {
        return $this->wallData !== [] ? $this->wallData : $this->fallbackWallData();
    }

    private function loadWallData(): void
    {
        try {
            $this->wallData = app(DashboardMetricsService::class)->nocWallPayload();
        } catch (\Throwable $e) {
            report($e);
            $this->wallData = $this->fallbackWallData();
        }

        $this->wallReady = true;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackWallData(): array
    {
        return [
            'noc' => [
                'online_now' => 0,
                'user_down' => 0,
                'wan_download_mbps' => 0,
                'wan_upload_mbps' => 0,
                'users_download_mbps' => 0,
                'users_upload_mbps' => 0,
                'link_down' => 0,
                'olt_offline' => 0,
                'olt_partial' => 0,
                'fiber_alerts' => 0,
                'bandwidth_trend' => ['labels' => [], 'download_mbps' => [], 'upload_mbps' => []],
                'access_telemetry' => ['current' => [], 'trend' => ['labels' => [], 'ping_loss_percent' => [], 'pon_module_temp_c' => [], 'sfp_temp_c' => []], 'sfp_is_fallback' => true],
                'wan_interfaces' => [],
                'down_users' => [],
                'root_causes' => [],
                'zone_impact' => [],
                'area_impact' => [],
                'active_outages' => ['count' => 0, 'items' => []],
                'critical_onu_list' => [],
                'top_impact' => [],
                'hot_pon_ports' => [],
                'olt_reachability' => [],
            ],
            'gpon' => [
                'fiber_faults' => 0,
                'critical_onus' => 0,
                'open_alerts' => 0,
            ],
            'support' => [
                'open' => 0,
                'sla_breached' => 0,
            ],
            'alerts' => [],
        ];
    }
}
