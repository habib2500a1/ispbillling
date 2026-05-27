<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Filament\Pages\SubscriberTrafficMonitor;
use App\Filament\Resources\CustomerResource;
use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\PppSessionLog;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Services\Bandwidth\BandwidthSyncStatus;
use App\Services\Mikrotik\MikrotikLiveOnlineChecker;
use App\Support\BandwidthDirection;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnlineClientsMonitoring extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static string $view = 'filament.pages.online-clients-monitoring';

    protected static ?string $navigationLabel = 'Online clients';

    protected static ?string $title = 'Online clients';

    protected static ?string $navigationGroup = 'Network';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'online-clients';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
        $this->tableFilters = [
            'online_status' => ['value' => 'all'],
        ];

        // Do not run refreshOnlineFlagsForTenant on mount — it can wipe is_ppp_online when
        // router probes are stale while real PPP sessions are still active.
    }

    public function refreshLiveData(): void
    {
        if ((int) config('bandwidth.live_page_poll_seconds', 60) <= 0) {
            return;
        }

        $tenantId = TenantResolver::requiredTenantId();
        $service = app(BandwidthCollectionService::class);

        if (config('bandwidth.online_clients_collect_on_poll', false)
            && config('bandwidth.collection_enabled', true)
            && $service->tenantHasEnabledMikrotik($tenantId)) {
            try {
                $service->collectForTenant($tenantId);
            } catch (\Throwable) {
                // Keep last-known online flags; scheduler / Sync live sessions handle recovery.
            }
        }

        $this->resetTable();
    }

    /**
     * @return array{total: int, online: int, offline: int, active_sessions: int, unmatched_hint: bool, flags_trustworthy: bool, sync_stale: bool}
     */
    public function getMonitoringStats(): array
    {
        $tenantId = TenantResolver::requiredTenantId();

        $base = Customer::query()
            ->where('tenant_id', $tenantId)
            ->withMikrotikPpp();

        $total = (clone $base)->count();

        $bandwidth = app(BandwidthCollectionService::class);
        $flagsTrustworthy = $bandwidth->tenantOnlineFlagsTrustworthy($tenantId);
        $online = $bandwidth->displayedOnlineCount($tenantId, $base);

        $activeSessions = PppSessionLog::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $sync = BandwidthSyncStatus::get($tenantId);
        $apiSessions = (int) ($sync['api']['sessions'] ?? 0);

        return [
            'total' => $total,
            'online' => $online,
            'offline' => max(0, $total - $online),
            'active_sessions' => $activeSessions,
            'unmatched_hint' => $apiSessions > $online && $online === 0,
            'flags_trustworthy' => $flagsTrustworthy,
            'sync_stale' => ! $flagsTrustworthy && $online > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSyncStatus(): array
    {
        return BandwidthSyncStatus::get(TenantResolver::requiredTenantId());
    }

    /**
     * @return array<int|string, string>
     */
    protected function mikrotikServerFilterOptions(): array
    {
        return MikrotikServer::query()
            ->where('tenant_id', TenantResolver::requiredTenantId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function applyOnlineStatusTableFilter(Builder $query, array $data): Builder
    {
        $value = $data['value'] ?? 'all';

        if ($value === 'all') {
            return $query;
        }

        return app(BandwidthCollectionService::class)->applyDisplayedOnlineFilter(
            $query,
            TenantResolver::requiredTenantId(),
            $value === 'online',
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->where('tenant_id', TenantResolver::requiredTenantId())
                    ->withMikrotikPpp()
                    ->with([
                        'zone',
                        'subzone',
                        'package',
                        'mikrotikServer:id,name,host,last_api_status',
                        'activePppSession',
                        'latestPppSession',
                        'lastEndedPppSession',
                    ])
            )
            ->defaultSort('is_ppp_online', 'desc')
            ->searchPlaceholder('Search code, name, phone, PPP user, IP…')
            ->columns([
                Tables\Columns\IconColumn::make('live_session')
                    ->label('')
                    ->icon(fn (Customer $record): string => $this->liveOnlineIcon($record))
                    ->color(fn (Customer $record): string => $this->liveOnlineColor($record))
                    ->tooltip(fn (Customer $record): string => $this->liveOnlineTooltip($record))
                    ->visible(fn (): bool => app(MikrotikLiveOnlineChecker::class)->enabled()),
                Tables\Columns\TextColumn::make('connection_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Customer $record): string => $this->connectionStatusLabel($record))
                    ->color(fn (Customer $record): string => $this->connectionStatusColor($record))
                    ->icon(fn (Customer $record): string => $record->isPppOnline()
                        ? 'heroicon-o-signal'
                        : 'heroicon-o-signal-slash'),
                Tables\Columns\TextColumn::make('customer_code')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('ppp_login')
                    ->label('PPP user')
                    ->state(fn (Customer $record): string => $record->pppLoginName())
                    ->fontFamily('mono')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search): void {
                            $q->where('mikrotik_secret_name', 'like', "%{$search}%")
                                ->orWhere('radius_username', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Mobile')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('activePppSession.framed_ip')
                    ->label('Client IP')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('activePppSession', fn (Builder $q) => $q
                            ->where('framed_ip', 'like', "%{$search}%"));
                    }),
                Tables\Columns\TextColumn::make('activePppSession.caller_id')
                    ->label('MAC / Caller ID')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('mikrotikServer.host')
                    ->label('Router (NAS)')
                    ->placeholder('—')
                    ->description(fn (Customer $record): ?string => $record->mikrotikServer?->name)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('session_login_at')
                    ->label('Login')
                    ->state(function (Customer $record): ?string {
                        $at = $record->activePppSession?->started_at
                            ?? $record->latestPppSession?->started_at;

                        return $at?->format('d M Y H:i');
                    })
                    ->description(fn (Customer $record): ?string => $record->isPppOnline()
                        ? 'Active session'
                        : ($record->latestPppSession ? 'Last session' : null))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            PppSessionLog::query()
                                ->select('started_at')
                                ->whereColumn('customer_id', 'customers.id')
                                ->latest('started_at')
                                ->limit(1),
                            $direction,
                        );
                    }),
                Tables\Columns\TextColumn::make('lastEndedPppSession.ended_at')
                    ->label('Last logout')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('session_duration')
                    ->label('Uptime')
                    ->state(function (Customer $record): string {
                        $session = $record->activePppSession;

                        return $session ? $session->formattedDuration() : '—';
                    }),
                Tables\Columns\TextColumn::make('live_download')
                    ->label('Live ↓')
                    ->state(fn (Customer $record): ?int => $record->activePppSession?->liveDownloadBps())
                    ->formatStateUsing(fn (?int $state): string => BandwidthDirection::formatBps($state))
                    ->description(fn (Customer $record): string => '↑ '.BandwidthDirection::formatBps($record->activePppSession?->liveUploadBps()))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('activePppSession.bytes_in')
                    ->label('Session ↓')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) ($state ?? 0)))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('activePppSession.bytes_out')
                    ->label('Session ↑')
                    ->formatStateUsing(fn ($state): string => BandwidthUsageDaily::formatBytes((int) ($state ?? 0)))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ppp_last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('mikrotik_server_id')
                    ->label('Router')
                    ->options(fn (): array => $this->mikrotikServerFilterOptions()),
                Tables\Filters\SelectFilter::make('online_status')
                    ->label('Status')
                    ->options([
                        'online' => 'Online only',
                        'offline' => 'Offline only',
                        'all' => 'All PPP users',
                    ])
                    ->default('all')
                    ->query(fn (Builder $query, array $data): Builder => $this->applyOnlineStatusTableFilter($query, $data)),
            ])
            ->filtersFormColumns(['default' => 1, 'sm' => 2])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\Action::make('traffic_graph')
                    ->label('Graph')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('info')
                    ->url(fn (Customer $record): string => SubscriberTrafficMonitor::getUrl([
                        'customer' => $record->getKey(),
                    ]))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('view')
                    ->label('Profile')
                    ->icon('heroicon-o-user')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No PPP subscribers yet')
            ->emptyStateDescription('Import from MikroTik or add PPP username on subscribers, then click Sync live sessions.')
            ->emptyStateIcon('heroicon-o-signal-slash')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync')
                ->label('Sync live sessions')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function (): void {
                    try {
                        $tenantId = TenantResolver::requiredTenantId();
                        $result = app(BandwidthCollectionService::class)->collectForTenant($tenantId);

                        $body = sprintf(
                            'Matched %d · Online %d · Router sessions %d',
                            $result['matched_subscribers'],
                            $result['sessions_open'],
                            $result['api_sessions'],
                        );

                        if ($result['unmatched_logins'] !== []) {
                            $body .= "\nUnmatched: ".implode(', ', array_slice($result['unmatched_logins'], 0, 5));
                        }

                        if (! $result['api_ok'] && $result['api_sessions'] === 0) {
                            Notification::make()
                                ->title('MikroTik API unreachable')
                                ->body($body)
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Sync complete')
                                ->body($body)
                                ->success()
                                ->send();
                        }

                        $this->resetTable();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('bandwidth')
                ->label('Bandwidth monitor')
                ->icon('heroicon-o-chart-bar')
                ->url(BandwidthMonitor::getUrl())
                ->color('gray'),
        ];
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canMikrotik();
    }

    private function liveOnlineState(Customer $record): ?bool
    {
        return app(MikrotikLiveOnlineChecker::class)->checkCustomer($record);
    }

    private function liveOnlineIcon(Customer $record): string
    {
        $live = $this->liveOnlineState($record);

        return match ($live) {
            true => 'heroicon-o-signal',
            false => 'heroicon-o-signal-slash',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    private function liveOnlineColor(Customer $record): string
    {
        $live = $this->liveOnlineState($record);

        return match ($live) {
            true => 'success',
            false => 'gray',
            default => 'warning',
        };
    }

    private function liveOnlineTooltip(Customer $record): string
    {
        $live = $this->liveOnlineState($record);
        $polled = $record->isPppOnline() ? 'online' : 'offline';

        return match ($live) {
            true => "Live API: online (polled: {$polled})",
            false => "Live API: offline (polled: {$polled})",
            default => "Live API unreachable (polled: {$polled})",
        };
    }

    private function connectionStatusLabel(Customer $record): string
    {
        if (app(MikrotikLiveOnlineChecker::class)->enabled()) {
            $live = $this->liveOnlineState($record);

            return match ($live) {
                true => 'Online',
                false => 'Offline',
                default => $record->isPppOnline() ? 'Online (poll)' : 'Offline (poll)',
            };
        }

        return $record->isPppOnline() ? 'Online' : 'Offline';
    }

    private function connectionStatusColor(Customer $record): string
    {
        if (app(MikrotikLiveOnlineChecker::class)->enabled()) {
            $live = $this->liveOnlineState($record);

            return match ($live) {
                true => 'success',
                false => 'gray',
                default => $record->isPppOnline() ? 'success' : 'gray',
            };
        }

        return $record->isPppOnline() ? 'success' : 'gray';
    }
}
