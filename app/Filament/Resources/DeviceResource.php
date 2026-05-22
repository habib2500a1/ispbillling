<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Device;
use App\Models\OltPort;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?int $navigationSort = 11;

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->nonOlts();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->options([
                        'onu' => 'ONU',
                        'olt' => 'OLT',
                        'router' => 'Router',
                        'switch' => 'Switch',
                    ])
                    ->required()
                    ->default('onu'),
                Forms\Components\Select::make('vendor')
                    ->options([
                        'huawei' => 'Huawei',
                        'zte' => 'ZTE',
                        'fiberhome' => 'Fiberhome',
                        'vsol' => 'VSOL',
                        'alcatel' => 'Alcatel-Lucent / Nokia',
                        'nokia' => 'Nokia',
                        'bdcom' => 'BDCom',
                        'cdata' => 'C-Data',
                        'other' => 'Other',
                    ])
                    ->searchable()
                    ->helperText('CLI/SNMP hints: config/olt_vendors.php (VSOL, BDCom, C-Data, …).'),
                Forms\Components\TextInput::make('display_name')
                    ->label('Label')
                    ->maxLength(255),
                Forms\Components\TextInput::make('location')
                    ->maxLength(255)
                    ->helperText('Optional site / POP note.'),
                Forms\Components\TextInput::make('serial_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mac_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('model')
                    ->maxLength(255),
                Forms\Components\Select::make('product_id')
                    ->label('Catalog product (stock link)')
                    ->relationship('catalogProduct', 'name')
                    ->searchable()
                    ->nullable()
                    ->helperText('Links CPE to inventory product for invoice hardware lines.'),
                Forms\Components\Select::make('olt_id')
                    ->label('Parent OLT')
                    ->relationship(
                        name: 'olt',
                        titleAttribute: 'serial_number',
                        modifyQueryUsing: fn (Builder $query) => $query->where('type', 'olt'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Device $record): string => $record->adminLabel())
                    ->searchable(['display_name', 'serial_number', 'management_ip'])
                    ->preload()
                    ->live(),
                Forms\Components\Select::make('olt_port_id')
                    ->label('Registered PON port')
                    ->options(function (Get $get): array {
                        $oltId = $get('olt_id');
                        if (! $oltId) {
                            return [];
                        }

                        return OltPort::query()
                            ->where('device_id', $oltId)
                            ->orderBy('card_index')
                            ->orderBy('pon_index')
                            ->get()
                            ->mapWithKeys(fn (OltPort $p): array => [$p->id => $p->label.' (#'.$p->id.')'])
                            ->all();
                    })
                    ->searchable(),
                Forms\Components\TextInput::make('management_ip')
                    ->label('Management IP')
                    ->maxLength(45),
                Forms\Components\TextInput::make('onu_external_id')
                    ->label('ONU external ID')
                    ->maxLength(64)
                    ->helperText('Vendor-specific auth / LOID / SNMP index — used by future OMCI/SNMP drivers.'),
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
                Forms\Components\TextInput::make('vlan_id')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4094),
                Forms\Components\TextInput::make('framed_ip_address')
                    ->label('Framed / static IP')
                    ->maxLength(45),
                Forms\Components\TextInput::make('rx_power_dbm')
                    ->label('RX power (dBm)')
                    ->numeric(),
                Forms\Components\TextInput::make('tx_power_dbm')
                    ->label('TX power (dBm)')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('provisioned_at'),
                Forms\Components\DateTimePicker::make('last_polled_at'),
                Forms\Components\Select::make('onu_oper_status')
                    ->label('ONU oper status (customer portal)')
                    ->options([
                        'unknown' => 'Unknown',
                        'online' => 'Online',
                        'offline' => 'Offline',
                        'los' => 'LOS / no optical',
                        'power_fail' => 'Power / ONT down',
                        'auth_fail' => 'Auth / registration fail',
                    ])
                    ->default('unknown')
                    ->visible(fn (Get $get): bool => $get('type') === 'onu'),
                Forms\Components\Textarea::make('offline_reason')
                    ->label('Offline / fault reason (shown to customer when set)')
                    ->rows(2)
                    ->maxLength(2000)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('type') === 'onu'),
                Forms\Components\Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Forms\Components\Section::make('Lease & binding')
                    ->schema([
                        Forms\Components\Select::make('lease_status')
                            ->options([
                                'none' => 'None',
                                'active' => 'Active lease',
                                'returned' => 'Returned',
                            ])
                            ->default('none')
                            ->required(),
                        Forms\Components\TextInput::make('lease_monthly_fee')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('BDT'),
                        Forms\Components\DateTimePicker::make('lease_started_at'),
                        Forms\Components\DateTimePicker::make('lease_ended_at'),
                        Forms\Components\Toggle::make('mac_binding_strict'),
                        Forms\Components\Toggle::make('serial_binding_strict'),
                        Forms\Components\TextInput::make('authorization_password')
                            ->label('ONU auth password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Select::make('status')
                    ->options([
                        'in_stock' => 'In stock',
                        'assigned' => 'Assigned',
                        'faulty' => 'Faulty',
                    ])
                    ->required()
                    ->default('in_stock'),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('meta')
                    ->label('Vendor meta (JSON)')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Automated portal status: set keys portal_onu_oper_status and portal_offline_reason (or see config/olt_vendors.php device_meta_portal_keys), then run isp:sync-onu-status-from-meta on a schedule.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('vendor')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Label')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mac_address')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model')
                    ->searchable(),
                Tables\Columns\TextColumn::make('management_ip')
                    ->label('Mgmt IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rx_power_dbm')
                    ->label('RX dBm')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('olt_label')
                    ->label('OLT')
                    ->getStateUsing(fn (Device $record): string => $record->olt?->adminLabel() ?? '—'),
                Tables\Columns\TextColumn::make('oltPort.label')
                    ->label('PON')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('onu_oper_status')
                    ->label('ONU status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('offline_reason')
                    ->label('Offline reason')
                    ->limit(40)
                    ->tooltip(fn (Device $record): ?string => $record->offline_reason)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lease_status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('card_no')
                    ->label('C')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pon_no')
                    ->label('P')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('onu_index')
                    ->label('ONU#')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('customer.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('stub_onu_ops')
                    ->label('ONU ops (stub)')
                    ->icon('heroicon-o-bolt')
                    ->visible(fn (Device $record): bool => $record->type === 'onu')
                    ->form([
                        Forms\Components\Select::make('op')
                            ->options([
                                'reboot' => 'Remote reboot',
                                'profile' => 'Push profile (placeholder)',
                                'firmware' => 'Firmware upgrade (placeholder)',
                            ])
                            ->required(),
                    ])
                    ->action(function (Device $record, array $data): void {
                        Notification::make()
                            ->title('ONU operation (not wired)')
                            ->body('Operation: '.($data['op'] ?? '').' — connect vendor OLT/OMCI driver to execute.')
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('sync_network_access')
                        ->label('Sync network access')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $seen = [];
                            foreach ($records as $device) {
                                if (! $device->customer_id) {
                                    continue;
                                }
                                $key = $device->tenant_id.'-'.$device->customer_id;
                                if (isset($seen[$key])) {
                                    continue;
                                }
                                $seen[$key] = true;
                                SyncCustomerNetworkAccessJob::dispatch((int) $device->tenant_id, (int) $device->customer_id)->afterResponse();
                            }
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
