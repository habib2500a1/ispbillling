<?php

namespace App\Filament\Resources\OltResource\RelationManagers;

use App\Filament\Resources\CustomerResource;
use App\Models\Device;
use App\Models\OltPort;
use App\Services\Network\BdcomEponOnuSyncService;
use App\Services\Network\GponIntelligenceService;
use App\Services\Optical\OnuBulkTicketService;
use App\Services\Optical\OnuSignalCollectionService;
use App\Support\OnuSignalLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OnusRelationManager extends RelationManager
{
    protected static string $relationship = 'onus';

    protected static ?string $title = 'ONU optical power (dBm per subscriber)';

    protected static ?string $icon = 'heroicon-o-signal';

    protected static bool $isLazy = true;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('type')->default('onu'),
                Forms\Components\TextInput::make('display_name')
                    ->label('Label')
                    ->maxLength(255),
                Forms\Components\TextInput::make('serial_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mac_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('model')
                    ->maxLength(255),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('olt_port_id')
                    ->label('PON port (registered)')
                    ->options(function (): array {
                        return OltPort::query()
                            ->where('device_id', $this->getOwnerRecord()->getKey())
                            ->orderBy('card_index')
                            ->orderBy('pon_index')
                            ->get()
                            ->mapWithKeys(fn (OltPort $p): array => [$p->id => $p->label.' (#'.$p->id.')'])
                            ->all();
                    })
                    ->searchable(),
                Forms\Components\TextInput::make('card_no')
                    ->label('Line card #')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255),
                Forms\Components\TextInput::make('pon_no')
                    ->label('PON port #')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(255),
                Forms\Components\TextInput::make('onu_index')
                    ->label('ONU index')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(65535),
                Forms\Components\Select::make('onu_oper_status')
                    ->label('ONU oper status')
                    ->options([
                        'unknown' => 'Unknown',
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'los' => 'LOS / no optical',
                        'power_fail' => 'Power / ONT down',
                        'auth_fail' => 'Auth / registration fail',
                    ])
                    ->default('unknown'),
                Forms\Components\Textarea::make('offline_reason')
                    ->label('Offline / fault reason')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('onu_external_id')
                    ->label('External / LOID ID')
                    ->maxLength(64),
                Forms\Components\TextInput::make('vlan_id')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4094),
                Forms\Components\TextInput::make('framed_ip_address')
                    ->label('Static / framed IP')
                    ->maxLength(45),
                Forms\Components\TextInput::make('rx_power_dbm')
                    ->label('RX (dBm)')
                    ->numeric(),
                Forms\Components\TextInput::make('tx_power_dbm')
                    ->label('TX (dBm)')
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        'in_stock' => 'In stock',
                        'assigned' => 'Assigned',
                        'faulty' => 'Faulty',
                    ])
                    ->required()
                    ->default('assigned'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->heading('BDCOM EPON — all ONUs')
            ->description('Sync from OLT SNMP (MAC, RX/TX dBm, online/offline). Offline MAC rows can be bulk-deleted from inventory.')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('type', 'onu')
                ->select([
                    'id', 'tenant_id', 'olt_id', 'customer_id', 'type', 'display_name', 'serial_number',
                    'mac_address', 'card_no', 'pon_no', 'onu_index', 'rx_power_dbm', 'tx_power_dbm',
                    'onu_oper_status', 'status', 'last_polled_at',
                ])
                ->with([
                    'customer:id,customer_code,name,phone,mikrotik_secret_name,radius_username',
                    'onuHealthScore:device_id,health_score',
                ]))
            ->defaultSort('rx_power_dbm', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('PON port')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mac_address')
                    ->label('MAC')
                    ->copyable()
                    ->fontFamily('mono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.customer_code')
                    ->label('Code')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Subscriber')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Mobile')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ppp_login')
                    ->label('PPP login')
                    ->state(fn (Device $record): string => $record->customer?->pppLoginName() ?? '—')
                    ->fontFamily('mono')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rx_power_dbm')
                    ->label('RX dBm')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).' dBm' : '—')
                    ->badge()
                    ->color(fn (Device $record): string => OnuSignalLevel::filamentColor(
                        OnuSignalLevel::classifyRx(
                            $record->rx_power_dbm !== null ? (float) $record->rx_power_dbm : null,
                            strtolower((string) ($record->onu_oper_status ?? '')),
                        ),
                    )),
                Tables\Columns\TextColumn::make('tx_power_dbm')
                    ->label('TX dBm')
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).' dBm' : '—')
                    ->badge()
                    ->color(fn (Device $record): string => OnuSignalLevel::filamentColor(
                        OnuSignalLevel::classifyTx($record->tx_power_dbm !== null ? (float) $record->tx_power_dbm : null),
                    )),
                Tables\Columns\TextColumn::make('rx_level')
                    ->label('Signal')
                    ->badge()
                    ->state(function (Device $record): string {
                        $level = OnuSignalLevel::classifyRx(
                            $record->rx_power_dbm !== null ? (float) $record->rx_power_dbm : null,
                            strtolower((string) ($record->onu_oper_status ?? '')),
                        );

                        return OnuSignalLevel::labels()[$level] ?? $level;
                    })
                    ->color(fn (Device $record): string => OnuSignalLevel::filamentColor(
                        OnuSignalLevel::classifyRx(
                            $record->rx_power_dbm !== null ? (float) $record->rx_power_dbm : null,
                            strtolower((string) ($record->onu_oper_status ?? '')),
                        ),
                    )),
                Tables\Columns\TextColumn::make('health_score')
                    ->label('Health')
                    ->suffix('%')
                    ->placeholder('—')
                    ->state(fn (Device $record): ?int => $record->onuHealthScore?->health_score)
                    ->color(fn ($state): string => match (true) {
                        $state !== null && $state >= 85 => 'success',
                        $state !== null && $state >= 60 => 'warning',
                        $state !== null => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('onu_oper_status')
                    ->label('ONU')
                    ->badge()
                    ->color(fn (Device $record): string => in_array(
                        strtolower((string) $record->onu_oper_status),
                        ['online', 'active', 'up'],
                        true,
                    ) ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('pon_no')
                    ->label('PON')
                    ->formatStateUsing(fn (Device $r): string => trim(
                        ($r->card_no !== null ? 'C'.$r->card_no.'/' : '').($r->pon_no !== null ? 'P'.$r->pon_no : ''),
                        '/',
                    ) ?: '—')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('ONU serial')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_polled_at')
                    ->label('Updated')
                    ->since()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_rx')
                    ->label('Has RX data')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('rx_power_dbm')),
                Tables\Filters\Filter::make('weak_or_critical')
                    ->label('Weak / critical only')
                    ->query(function (Builder $query): Builder {
                        $critical = (float) config('optical.rx_thresholds.critical', -27);
                        $good = (float) config('optical.rx_thresholds.good', -15);

                        return $query->where(function (Builder $q) use ($critical, $good): void {
                            $q->where('rx_power_dbm', '<', $good)
                                ->orWhereIn('onu_oper_status', ['offline', 'los', 'power_fail']);
                        });
                    }),
                Tables\Filters\Filter::make('no_data')
                    ->label('No dBm data')
                    ->query(fn (Builder $query): Builder => $query->whereNull('rx_power_dbm')),
                Tables\Filters\Filter::make('offline_only')
                    ->label('Offline / LOS only')
                    ->query(fn (Builder $query): Builder => $query->whereIn('onu_oper_status', ['offline', 'los', 'power_fail'])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('sync_bdcom_epon')
                    ->label('Sync from BDCOM EPON')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('primary')
                    ->visible(fn (): bool => app(BdcomEponOnuSyncService::class)->supportsDriver($this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->modalDescription('SNMP walk on OLT — imports all ONUs with MAC, optical power, and status. May take 1–2 minutes.')
                    ->action(function (): void {
                        $olt = $this->getOwnerRecord();
                        try {
                            $result = app(BdcomEponOnuSyncService::class)->syncOlt($olt->fresh(), false);
                            $notification = Notification::make()
                                ->title($result['success'] ? 'BDCOM sync complete' : 'BDCOM sync failed')
                                ->body($result['success']
                                    ? "Found {$result['discovered']} ONUs · created {$result['created']} · updated {$result['updated']}"
                                    : ($result['error'] ?? 'Unknown error'));
                            $result['success'] ? $notification->success() : $notification->danger();
                            $notification->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Sync error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('delete_offline_onus')
                    ->label('Delete offline MACs')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete offline ONU records?')
                    ->modalDescription('Removes inventory rows for ONUs marked offline/LOS on this OLT (does not reboot hardware).')
                    ->action(function (): void {
                        $deleted = Device::query()
                            ->where('olt_id', $this->getOwnerRecord()->getKey())
                            ->where('type', 'onu')
                            ->whereIn('onu_oper_status', ['offline', 'los', 'power_fail'])
                            ->delete();
                        Notification::make()
                            ->title('Offline ONUs removed')
                            ->body("Deleted {$deleted} record(s) from inventory.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('sync_optical')
                    ->label('Sync all ONU dBm')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (): void {
                        $olt = $this->getOwnerRecord();
                        try {
                            app(GponIntelligenceService::class)->syncAllOnuOpticalForOlt($olt);
                            $tenantId = (int) $olt->tenant_id;
                            $result = app(OnuSignalCollectionService::class)->collectForTenant($tenantId);
                            Notification::make()
                                ->title('ONU optical sync done')
                                ->body(sprintf('Logged %d snapshots · %d alerts', $result['logged'], $result['alerts']))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Sync failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['olt_id'] = $this->getOwnerRecord()->getKey();
                        $data['type'] = 'onu';

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('subscriber')
                    ->label('Subscriber')
                    ->icon('heroicon-o-user')
                    ->visible(fn (Device $record): bool => $record->customer_id !== null)
                    ->url(fn (Device $record): string => CustomerResource::getUrl('view', ['record' => $record->customer_id])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('create_tickets_weak')
                        ->label('Create support tickets (weak signal)')
                        ->icon('heroicon-o-lifebuoy')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $created = app(OnuBulkTicketService::class)->createTicketsForWeakOnus($records);
                            Notification::make()
                                ->title('Support tickets')
                                ->body("Created {$created} ticket(s) for weak/critical ONUs with subscribers.")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('delete_offline_selected')
                        ->label('Delete offline selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $ids = $records
                                ->filter(fn (Device $d): bool => in_array(strtolower((string) $d->onu_oper_status), ['offline', 'los', 'power_fail'], true))
                                ->pluck('id');
                            $deleted = Device::query()->whereIn('id', $ids)->delete();
                            Notification::make()
                                ->title('Deleted offline ONUs')
                                ->body("Removed {$deleted} record(s).")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No ONUs on this OLT')
            ->emptyStateDescription('Import from EMS webhook (olt_id + readings) or add ONU manually.')
            ->paginated([25, 50, 100, 200]);
    }
}
