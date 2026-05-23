<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Filament\Resources\OltResource;
use App\Models\Device;
use App\Models\SignalAlert;
use App\Services\Optical\OnuSignalCollectionService;
use App\Services\Optical\OpticalDashboardService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\OltNocDashboardService;
use App\Services\Olt\OltProvisioningService;
use App\Services\Optical\OpticalDatabasePresenter;
use App\Services\Optical\OpticalTopologyService;
use App\Services\Optical\OpticalNocDashboardService;
use App\Services\Optical\OpticalSignalHistoryService;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OpticalMonitoringHub extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static string $view = 'filament.pages.optical-monitoring-hub';

    protected static ?string $navigationLabel = 'Optical Database';

    protected static ?string $title = 'Optical Database';

    protected static ?string $navigationGroup = 'Network';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'optical-noc';

    public string $monitorTab = 'database';

    public string $opticalDbSearch = '';

    public int $opticalDbPerPage = 25;

    public int $opticalDbPage = 1;

    /**
     * @return array<string, mixed>
     */
    public function getNocPayload(): array
    {
        try {
            return app(OpticalNocDashboardService::class)->fullSnapshot(TenantResolver::requiredTenantId());
        } catch (\Throwable) {
            return $this->getOpticalStatsSafe();
        }
    }

    public function mount(): void
    {
        $this->monitorTab = 'database';

        if (config('optical.auto_fetch_ppp_sessions', true) && config('bandwidth.collection_enabled', true)) {
            $tenantId = TenantResolver::requiredTenantId();
            $bandwidth = app(\App\Services\Bandwidth\BandwidthCollectionService::class);
            if ($bandwidth->tenantHasEnabledMikrotik($tenantId) && ! $bandwidth->tenantOnlineFlagsTrustworthy($tenantId)) {
                try {
                    $bandwidth->refreshOnlineFlagsForTenant($tenantId);
                } catch (\Throwable) {
                    // Optical table still loads from OLT inventory.
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getOpticalStats(): array
    {
        return app(OpticalDashboardService::class)->snapshot(TenantResolver::requiredTenantId());
    }

    /**
     * @return array<string, mixed>
     */
    public function getOpticalStatsSafe(): array
    {
        try {
            return $this->getOpticalStats();
        } catch (\Throwable) {
            return [
                'total_onus' => 0,
                'online_onus' => 0,
                'critical_onus' => 0,
                'warning_onus' => 0,
                'offline_onus' => 0,
                'excellent_onus' => 0,
                'avg_rx_dbm' => null,
                'open_alerts' => 0,
                'fiber_faults' => 0,
                'avg_health' => 0,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getOltHealthPayload(): array
    {
        try {
            return app(OltNocDashboardService::class)->snapshot(TenantResolver::requiredTenantId());
        } catch (\Throwable) {
            return ['olt_total' => 0, 'olts' => []];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopologyPayload(): array
    {
        try {
            return app(OpticalTopologyService::class)->buildForTenant(TenantResolver::requiredTenantId());
        } catch (\Throwable) {
            return ['summary' => ['olts' => 0, 'onus' => 0], 'olts' => []];
        }
    }

    public function setMonitorTab(string $tab): void
    {
        if (! in_array($tab, ['database', 'olt', 'topology', 'charts', 'pon', 'ai', 'alerts'], true)) {
            return;
        }

        $this->monitorTab = $tab;
    }

    /**
     * @return \Illuminate\Support\Collection<int, SignalAlert>
     */
    public function getOpenAlertsPayload(): \Illuminate\Support\Collection
    {
        try {
            return SignalAlert::query()
                ->where('tenant_id', TenantResolver::requiredTenantId())
                ->where('status', 'open')
                ->with('device:id,serial_number')
                ->orderByDesc('detected_at')
                ->limit(50)
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function updatedOpticalDbSearch(): void
    {
        $this->opticalDbPage = 1;
    }

    public function updatedOpticalDbPerPage(): void
    {
        $this->opticalDbPage = 1;
    }

    public function gotoOpticalDbPage(int $page): void
    {
        $this->opticalDbPage = max(1, $page);
    }

    /**
     * @return array{labels: list<string>, avg_rx: list<float|null>, weak_count: list<int>}
     */
    public function getTrend24hPayload(): array
    {
        try {
            return app(OpticalSignalHistoryService::class)->tenantAverageTrend(
                TenantResolver::requiredTenantId(),
                24,
            );
        } catch (\Throwable) {
            return ['labels' => [], 'avg_rx' => [], 'weak_count' => []];
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getPonPortsPayload(): \Illuminate\Support\Collection
    {
        try {
            return app(OpticalSignalHistoryService::class)->ponPortStats(
                TenantResolver::requiredTenantId(),
                20,
            );
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAiWarningsPayload(): array
    {
        try {
            return \App\Models\SignalPrediction::query()
                ->where('tenant_id', TenantResolver::requiredTenantId())
                ->whereIn('risk_level', ['high', 'critical', 'warning'])
                ->orderByDesc('risk_score')
                ->limit(15)
                ->get()
                ->map(fn (\App\Models\SignalPrediction $p): array => [
                    'summary' => $p->summary,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{summary: array<string, int>, rows: \Illuminate\Contracts\Pagination\LengthAwarePaginator}
     */
    public function getOpticalDatabasePayload(): array
    {
        try {
            $tenantId = TenantResolver::requiredTenantId();
            $presenter = app(OpticalDatabasePresenter::class);

            return [
                'summary' => $presenter->summary($tenantId),
                'rows' => $presenter->paginate(
                    $tenantId,
                    $this->opticalDbSearch !== '' ? $this->opticalDbSearch : null,
                    $this->opticalDbPerPage,
                    max(1, $this->opticalDbPage),
                ),
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'summary' => ['total' => 0, 'with_rx' => 0, 'linked' => 0],
                'rows' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
            ];
        }
    }

    protected function getHeaderActions(): array
    {
        $driverOptions = collect(config('olt_drivers.drivers', []))
            ->mapWithKeys(fn (array $cfg, string $key): array => [$key => (string) ($cfg['label'] ?? $key)])
            ->all();

        return [
            Action::make('add_olt')
                ->label('Add OLT')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn (): bool => OltResource::canCreate())
                ->modalHeading('নতুন OLT যোগ করুন')
                ->modalDescription('Huawei GPON / BDCOM — IP + SNMP community দিন। Save এর পর প্রথম SNMP poll চলবে (ONU dBm auto)।')
                ->modalWidth('lg')
                ->form([
                    Forms\Components\TextInput::make('display_name')
                        ->label('OLT name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Core-OLT-Dhk'),
                    Forms\Components\TextInput::make('management_ip')
                        ->label('IP address')
                        ->required()
                        ->maxLength(45)
                        ->placeholder('103.29.127.90'),
                    Forms\Components\TextInput::make('snmp_community')
                        ->label('SNMP community (v2c)')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('your_read_community'),
                    Forms\Components\Select::make('olt_driver')
                        ->label('OLT type')
                        ->options($driverOptions)
                        ->default('huawei_gpon')
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('serial_number')
                        ->label('Serial (optional)')
                        ->maxLength(255)
                        ->helperText('খালি = IP থেকে auto'),
                    Forms\Components\TextInput::make('location')
                        ->label('Location')
                        ->maxLength(255),
                    Forms\Components\Toggle::make('poll_after_create')
                        ->label('Poll SNMP immediately (ONU dBm + OLT health)')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = app(OltProvisioningService::class)->createQuick(
                            TenantResolver::requiredTenantId(),
                            $data,
                            (bool) ($data['poll_after_create'] ?? true),
                        );
                        $olt = $result['olt'];
                        $poll = $result['poll'];
                        $body = 'OLT #'.$olt->id.' · '.$olt->management_ip;
                        if (is_array($poll)) {
                            if ($poll['success'] ?? false) {
                                $body .= sprintf(
                                    ' · ONUs %d online',
                                    (int) ($poll['onus_online'] ?? 0),
                                );
                                if (! empty($poll['huawei_onu_discovered'])) {
                                    $body .= ' · Huawei '.$poll['huawei_onu_discovered'].' ONU';
                                }
                                if (! empty($poll['bdcom_onu_discovered'])) {
                                    $body .= ' · BDCOM '.$poll['bdcom_onu_discovered'].' ONU';
                                }
                            } else {
                                $body .= ' · SNMP: '.($poll['error'] ?? 'failed');
                            }
                        }
                        Notification::make()
                            ->title('OLT added ✓')
                            ->body($body)
                            ->success()
                            ->send();
                        $this->monitorTab = 'olt';
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Could not add OLT')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('manage_olts')
                ->label('All OLTs')
                ->icon('heroicon-o-server-stack')
                ->color('gray')
                ->url(fn (): string => OltResource::getUrl('index'))
                ->visible(fn (): bool => OltResource::canViewAny()),
            Action::make('laser_thresholds')
                ->label('Laser thresholds')
                ->icon('heroicon-o-adjustments-vertical')
                ->color('gray')
                ->url(fn (): string => ManageOpticalLaserSettings::getUrl())
                ->visible(fn (): bool => ManageOpticalLaserSettings::canAccess()),
            Action::make('poll_olts')
                ->label('Poll OLT health')
                ->icon('heroicon-o-server-stack')
                ->color('warning')
                ->action(function (): void {
                    $tenantId = TenantResolver::requiredTenantId();
                    $olts = Device::query()
                        ->where('tenant_id', $tenantId)
                        ->olts()
                        ->where('status', '!=', 'decommissioned')
                        ->get();
                    $monitor = app(OltSnmpMonitorService::class);
                    $ok = 0;
                    foreach ($olts as $olt) {
                        $r = $monitor->pollOlt($olt);
                        if ($r['success']) {
                            $ok++;
                        }
                    }
                    Notification::make()
                        ->title('OLT poll complete')
                        ->body("{$ok}/{$olts->count()} OLT(s) responded via SNMP")
                        ->success()
                        ->send();
                }),
            Action::make('sync')
                ->label('Sync optical data')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    try {
                        $result = app(OnuSignalCollectionService::class)->collectForTenant(TenantResolver::requiredTenantId());
                        Notification::make()
                            ->title('Optical sync complete')
                            ->body(sprintf('%d snapshots · %d alerts', $result['logged'], $result['alerts']))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canOlt();
    }
}
