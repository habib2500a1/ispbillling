<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\OltResource;
use App\Models\Customer;
use App\Models\Device;
use App\Models\SignalAlert;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Services\Optical\OnuSignalCollectionService;
use App\Services\Optical\OpticalDashboardService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\OltHealthHistoryService;
use App\Services\Olt\OltNocDashboardService;
use App\Services\Olt\OltProvisioningService;
use App\Services\Optical\OpticalDatabasePresenter;
use App\Services\Optical\OpticalTopologyService;
use App\Services\Optical\OpticalNocDashboardService;
use App\Services\Optical\OpticalSignalHistoryService;
use App\Support\OnuSignalLevel;
use App\Support\OpticalThresholds;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
class OpticalMonitoringHub extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-light-bulb';

    protected static string $view = 'filament.pages.optical-monitoring-hub';

    protected static ?string $navigationLabel = 'Optical NOC';

    protected static ?string $title = 'ONU optical monitoring';

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
        $this->mountInteractsWithTable();
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
        if ($tab === 'alerts') {
            $this->resetTable();
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
    }

    public function table(Table $table): Table
    {
        $tenantId = TenantResolver::requiredTenantId();

        if ($this->monitorTab === 'database') {
            return $table->query(Device::query()->whereRaw('1 = 0'));
        }

        if ($this->monitorTab === 'alerts') {
            return $table
                ->query(
                    SignalAlert::query()
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'open')
                        ->orderByDesc('detected_at')
                )
                ->columns([
                    Tables\Columns\TextColumn::make('severity')->badge(),
                    Tables\Columns\TextColumn::make('title')->searchable(),
                    Tables\Columns\TextColumn::make('alert_type')->label('Type'),
                    Tables\Columns\TextColumn::make('device.serial_number')->label('ONU')->placeholder('—'),
                    Tables\Columns\TextColumn::make('rx_power_dbm')->label('RX dBm'),
                    Tables\Columns\TextColumn::make('detected_at')->since(),
                ])
                ->paginated([25, 50]);
        }

        return $table
            ->query(
                Device::query()
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'onu')
                    ->with(['customer', 'olt', 'onuHealthScore'])
            )
            ->defaultSort('rx_power_dbm', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('serial_number')->label('ONU serial')->searchable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber')->placeholder('—'),
                Tables\Columns\TextColumn::make('olt.display_name')->label('OLT')->placeholder('—'),
                Tables\Columns\TextColumn::make('pon_label')
                    ->label('PON')
                    ->state(fn (Device $r): string => trim(($r->card_no !== null ? 'C'.$r->card_no : '').'/'.($r->pon_no !== null ? 'P'.$r->pon_no : ''), '/'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('rx_power_dbm')
                    ->label('RX dBm')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).' dBm' : '—')
                    ->badge()
                    ->color(fn (Device $r): string => OnuSignalLevel::filamentColor(
                        OnuSignalLevel::classifyRx(
                            $r->rx_power_dbm !== null ? (float) $r->rx_power_dbm : null,
                            strtolower((string) ($r->onu_oper_status ?? '')),
                        ),
                    )),
                Tables\Columns\TextColumn::make('tx_power_dbm')
                    ->label('TX dBm')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).' dBm' : '—')
                    ->badge()
                    ->color(fn (Device $r): string => OnuSignalLevel::filamentColor(
                        OnuSignalLevel::classifyTx($r->tx_power_dbm !== null ? (float) $r->tx_power_dbm : null),
                    )),
                Tables\Columns\TextColumn::make('onu_oper_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Device $r): string => in_array(strtolower((string) $r->onu_oper_status), ['online', 'active', 'up'], true) ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('onuHealthScore.health_score')
                    ->label('Health %')
                    ->suffix('%')
                    ->placeholder('—')
                    ->color(fn ($state): string => match (true) {
                        $state !== null && $state >= 85 => 'success',
                        $state !== null && $state >= 60 => 'warning',
                        $state !== null => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('onuHealthScore.root_cause_hint')
                    ->label('Diagnosis')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_polled_at')->since()->label('Updated'),
            ])
            ->filters([
                Tables\Filters\Filter::make('critical_only')
                    ->label('Critical / weak only')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                        $critical = (float) config('optical.rx_thresholds.critical', -27);
                        $q->where('rx_power_dbm', '<', $critical)
                            ->orWhereIn('onu_oper_status', ['offline', 'los', 'power_fail']);
                    })),
                Tables\Filters\Filter::make('unlinked_only')
                    ->label('Unlinked (no subscriber)')
                    ->query(fn (Builder $query): Builder => $query->whereNull('customer_id')),
                Tables\Filters\Filter::make('linked_only')
                    ->label('Linked to subscriber')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('customer_id')),
                Tables\Filters\Filter::make('laser_high')
                    ->label('Laser high (RX or TX)')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                        $rxHigh = OpticalThresholds::rxHighWarnAbove();
                        $txHigh = OpticalThresholds::txHighWarnAbove();
                        $q->where('rx_power_dbm', '>', $rxHigh)
                            ->orWhere('tx_power_dbm', '>', $txHigh);
                    })),
            ])
            ->actions([
                Tables\Actions\Action::make('link_subscriber')
                    ->label('Link subscriber')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn (Device $r): bool => $r->customer_id === null)
                    ->modalHeading('এই ONU-কে subscriber-এর সাথে যুক্ত করুন')
                    ->modalDescription(fn (Device $r): string => 'ONU: '.$r->display_name.' · Serial: '.$r->serial_number.' · RX: '.($r->rx_power_dbm !== null ? number_format((float) $r->rx_power_dbm, 2).' dBm' : '—'))
                    ->form([
                        Forms\Components\Select::make('customer_id')
                            ->label('Subscriber')
                            ->placeholder('Search subscriber by name or PPPoE...')
                            ->options(function () use ($tenantId): array {
                                return Customer::query()
                                    ->withoutGlobalScopes()
                                    ->where('tenant_id', $tenantId)
                                    ->orderBy('name')
                                    ->limit(500)
                                    ->get()
                                    ->mapWithKeys(fn (Customer $c): array => [
                                        $c->id => trim(($c->customer_code ? '['.$c->customer_code.'] ' : '').$c->name.' · '.($c->mikrotik_secret_name ?: $c->phone ?? '')),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Device $record, array $data): void {
                        $customer = Customer::query()->withoutGlobalScopes()->find($data['customer_id']);
                        if ($customer === null) {
                            Notification::make()->title('Subscriber not found')->danger()->send();

                            return;
                        }
                        Device::query()
                            ->where('customer_id', $customer->id)
                            ->where('type', 'onu')
                            ->where('id', '!=', $record->id)
                            ->update(['customer_id' => null]);

                        app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer($customer, (int) $record->id, 'manual', 100);
                        Notification::make()
                            ->title('ONU linked ✓')
                            ->body($record->display_name.' → '.$customer->name.' · RX '.($record->rx_power_dbm !== null ? number_format((float) $record->rx_power_dbm, 2).' dBm' : '—'))
                            ->success()
                            ->send();
                        $this->resetTable();
                    }),
                Tables\Actions\Action::make('unlink_subscriber')
                    ->label('Unlink')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Device $r): bool => $r->customer_id !== null)
                    ->requiresConfirmation()
                    ->action(function (Device $record): void {
                        $record->forceFill(['customer_id' => null])->saveQuietly();
                        Notification::make()->title('ONU unlinked')->success()->send();
                        $this->resetTable();
                    }),
                Tables\Actions\Action::make('olt')
                    ->label('OLT')
                    ->icon('heroicon-o-server')
                    ->visible(fn (Device $r): bool => $r->olt_id !== null)
                    ->url(fn (Device $r): string => OltResource::getUrl('edit', ['record' => $r->olt_id])),
                Tables\Actions\Action::make('subscriber')
                    ->label('View subscriber')
                    ->icon('heroicon-o-user')
                    ->visible(fn (Device $r): bool => $r->customer_id !== null)
                    ->url(fn (Device $r): string => CustomerResource::getUrl('view', ['record' => $r->customer_id])),
                Tables\Actions\Action::make('signal_chart')
                    ->label('Optical graph')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalHeading('ONU optical power graph')
                    ->modalWidth('3xl')
                    ->modalContent(fn (Device $record): \Illuminate\Contracts\View\View => view(
                        'filament.pages.partials.onu-signal-chart-modal',
                        [
                            'device' => $record->loadMissing('customer'),
                            'series' => app(OpticalSignalHistoryService::class)->series((int) $record->id, '24h'),
                        ],
                    )),
            ])
            ->paginated([25, 50, 100]);
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
                        $this->resetTable();
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
