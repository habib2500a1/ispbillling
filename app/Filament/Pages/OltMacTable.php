<?php

namespace App\Filament\Pages;

use App\Models\Device;
use App\Services\Network\OltFdbMacBridgeService;
use App\Services\Olt\OltMacTableService;
use App\Services\Olt\OltPonMacTableService;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OltMacTable extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.pages.olt-mac-table';

    protected static ?string $navigationLabel = 'PON MAC Table';

    protected static ?string $title = 'PON MAC Table';

    protected static ?string $slug = 'olt-mac-table';

    protected static bool $shouldRegisterNavigation = false;

    public string $viewMode = 'pon';

    public ?string $filterOlt = null;

    public ?string $macTableSearch = null;

    /** Auto refresh interval in seconds. 0 = off. Range 5–3600 (Aveis-style). */
    public int $autoRefreshSeconds = 0;

    public bool $showAutoRefreshModal = false;

    public ?string $autoRefreshDraft = '0';

    public ?string $lastPonMacRefreshAt = null;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
        $this->autoRefreshSeconds = max(0, (int) session($this->autoRefreshSessionKey(), 0));
        $this->autoRefreshDraft = (string) $this->autoRefreshSeconds;
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canOlt();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['pon', 'onu'], true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->resetTable();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{olt_id: int, olt_label: string, mac_count: int, last_seen: ?Carbon}>
     */
    public function getOltSummaryProperty(): \Illuminate\Support\Collection
    {
        $oltId = filled($this->filterOlt) ? (int) $this->filterOlt : null;

        return $this->viewMode === 'pon'
            ? OltPonMacTableService::summaryByOlt($oltId)
            : OltMacTableService::summaryByOlt($oltId);
    }

    public function getTotalMacsProperty(): int
    {
        $oltId = filled($this->filterOlt) ? (int) $this->filterOlt : null;

        return $this->viewMode === 'pon'
            ? OltPonMacTableService::totalMacCount($oltId)
            : OltMacTableService::totalMacCount($oltId);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function getOltOptionsProperty(): array
    {
        return OltMacTableService::oltFilterOptions();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function getPonMacRowsProperty(): \Illuminate\Support\Collection
    {
        $oltId = filled($this->filterOlt) ? (int) $this->filterOlt : null;

        return OltPonMacTableService::rows($oltId, $this->macTableSearch);
    }

    public function applyFilters(): void
    {
        $this->resetTable();
    }

    public function resetFilters(): void
    {
        $this->filterOlt = null;
        $this->macTableSearch = null;
        $this->resetTable();
    }

    public function openAutoRefreshModal(): void
    {
        $this->autoRefreshDraft = (string) $this->autoRefreshSeconds;
        $this->showAutoRefreshModal = true;
    }

    public function closeAutoRefreshModal(): void
    {
        $this->showAutoRefreshModal = false;
    }

    public function applyAutoRefreshInterval(): void
    {
        $seconds = (int) trim((string) $this->autoRefreshDraft);
        if ($seconds < 0) {
            $seconds = 0;
        }
        if ($seconds > 0 && $seconds < 5) {
            $seconds = 5;
        }
        if ($seconds > 3600) {
            $seconds = 3600;
        }

        $this->autoRefreshSeconds = $seconds;
        session([$this->autoRefreshSessionKey() => $seconds]);
        $this->showAutoRefreshModal = false;

        Notification::make()
            ->title($seconds === 0 ? 'Auto refresh off' : 'Auto refresh enabled')
            ->body($seconds === 0
                ? 'PON MAC table will not auto refresh.'
                : "PON MAC will refresh every {$seconds}s (FDB only — ONU sync skipped).")
            ->success()
            ->send();
    }

    public function refreshPonMac(bool $silent = false): void
    {
        $fdb = app(OltFdbMacBridgeService::class);
        $query = Device::query()
            ->where('tenant_id', TenantResolver::requiredTenantId())
            ->olts()
            ->where('status', '!=', 'decommissioned');

        if (filled($this->filterOlt)) {
            $query->whereKey((int) $this->filterOlt);
        }

        $olts = $query->get();
        if ($olts->isEmpty()) {
            if (! $silent) {
                Notification::make()
                    ->title('No OLT selected')
                    ->body('Add an OLT first, or clear the OLT filter.')
                    ->warning()
                    ->send();
            }

            return;
        }

        $macsStored = 0;
        $fdbEntries = 0;
        $errors = [];

        foreach ($olts as $olt) {
            if (! $fdb->fdbEnabledFor($olt)) {
                $errors[] = $olt->adminLabel().': FDB bridge not enabled for this driver';

                continue;
            }

            $result = $fdb->collectForOlt($olt);
            if ($result['success']) {
                $macsStored += (int) ($result['macs_stored'] ?? 0);
                $fdbEntries += (int) ($result['fdb_entries'] ?? 0);
            } else {
                $errors[] = $olt->adminLabel().': '.($result['error'] ?? 'SNMP failed');
            }
        }

        $this->lastPonMacRefreshAt = now()->toIso8601String();

        if (! $silent) {
            $body = sprintf('%d MAC(s) on %d OLT(s) · %d FDB entries', $macsStored, $olts->count(), $fdbEntries);
            if ($errors !== []) {
                $body .= ' · '.implode('; ', array_slice($errors, 0, 2));
            }

            Notification::make()
                ->title($macsStored > 0 ? 'PON MAC refreshed' : 'PON MAC refresh complete')
                ->body($body)
                ->color($errors !== [] && $macsStored === 0 ? 'warning' : 'success')
                ->send();
        }
    }

    public function pollRefreshPonMac(): void
    {
        if ($this->viewMode !== 'pon' || $this->autoRefreshSeconds <= 0) {
            return;
        }

        $this->refreshPonMac(true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('olt_label')
                    ->label('OLT')
                    ->state(fn (Device $record): string => OltMacTableService::oltLabel($record))
                    ->searchable(false)
                    ->sortable(false)
                    ->weight('semibold'),
                Tables\Columns\TextColumn::make('mac_address')
                    ->label('MAC')
                    ->formatStateUsing(fn (?string $state): string => OltMacTableService::formatMac($state))
                    ->html()
                    ->state(fn (Device $record): string => '<span class="isp-olt-mac-pill">'.e(OltMacTableService::formatMac($record->mac_address)).'</span>')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('mac_address', $direction)),
                Tables\Columns\TextColumn::make('port_onu')
                    ->label('Port / ONU')
                    ->state(function (Device $record): string {
                        $port = e(OltMacTableService::portLabel($record));
                        $onu = OltMacTableService::onuIndexLabel($record);
                        if ($onu === null) {
                            return $port;
                        }

                        return $port.'<br><span class="text-xs text-gray-500 dark:text-gray-400">'.e($onu).'</span>';
                    })
                    ->html(),
                Tables\Columns\TextColumn::make('interface')
                    ->label('Interface')
                    ->state(fn (Device $record): string => OltMacTableService::interfaceLabel($record))
                    ->fontFamily('mono')
                    ->size('sm'),
                Tables\Columns\TextColumn::make('last_seen')
                    ->label('Last seen')
                    ->state(fn (Device $record): string => OltMacTableService::lastSeenAt($record)?->format('d/m/y H:i') ?? '—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('COALESCE(last_polled_at, last_snmp_poll_at, updated_at) '.$direction)),
                Tables\Columns\TextColumn::make('learned')
                    ->label('Learned')
                    ->state(fn (Device $record): string => OltMacTableService::learnedAt($record)?->format('d/m/y H:i') ?? '—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw('COALESCE(provisioned_at, created_at) '.$direction)),
            ])
            ->defaultSort('mac_address')
            ->paginated([50, 100, 200, 500])
            ->defaultPaginationPageOption(200)
            ->striped()
            ->emptyStateHeading('No ONU MAC entries yet')
            ->emptyStateDescription('Run OLT SNMP sync from OLT manage to populate ONU inventory MACs.');
    }

    protected function getTableQuery(): Builder
    {
        $query = OltMacTableService::baseQuery();

        if (filled($this->filterOlt)) {
            $query->where('olt_id', (int) $this->filterOlt);
        }

        if (filled($this->macTableSearch)) {
            OltMacTableService::applySearch($query, $this->macTableSearch);
        }

        return $query;
    }

    private function autoRefreshSessionKey(): string
    {
        return 'pon_mac_auto_refresh_'.auth()->id();
    }
}
