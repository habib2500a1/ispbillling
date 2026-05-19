<?php

namespace App\Filament\Pages;

use App\Models\Device;
use App\Services\Olt\OltMacTableService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OltMacTable extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.pages.olt-mac-table';

    protected static ?string $navigationLabel = 'OLT MAC Table';

    protected static ?string $title = 'OLT MAC Table';

    protected static ?string $slug = 'olt-mac-table';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $filterOlt = null;

    public ?string $macTableSearch = null;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{olt_id: int, olt_label: string, mac_count: int, last_seen: ?\Illuminate\Support\Carbon}>
     */
    public function getOltSummaryProperty(): \Illuminate\Support\Collection
    {
        $oltId = filled($this->filterOlt) ? (int) $this->filterOlt : null;

        return OltMacTableService::summaryByOlt($oltId);
    }

    public function getTotalMacsProperty(): int
    {
        $oltId = filled($this->filterOlt) ? (int) $this->filterOlt : null;

        return OltMacTableService::totalMacCount($oltId);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function getOltOptionsProperty(): array
    {
        return OltMacTableService::oltFilterOptions();
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
            ->emptyStateHeading('No MAC entries yet')
            ->emptyStateDescription('Run BDCOM OLT sync from OLT manage, or link ONUs from subscriber view.')
            ->poll('120s');
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
}
