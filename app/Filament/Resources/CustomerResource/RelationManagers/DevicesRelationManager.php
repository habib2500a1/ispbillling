<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Device;
use App\Models\OltPort;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DevicesRelationManager extends RelationManager
{
    protected static string $relationship = 'devices';

    protected static ?string $title = 'Connections & equipment';

    protected static ?string $icon = 'heroicon-o-signal';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('type')->default('onu'),
                Forms\Components\Select::make('connection_type')
                    ->label('Connection type')
                    ->options([
                        'fiber' => 'Fiber / GPON',
                        'wireless' => 'Wireless',
                        'dedicated' => 'Dedicated line',
                        'hotspot' => 'Hotspot',
                        'other' => 'Other',
                    ])
                    ->native(false),
                Forms\Components\Select::make('olt_id')
                    ->label('OLT')
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
                    ->searchable()
                    ->helperText('Optional: choose a PON port row from the selected OLT.'),
                Forms\Components\TextInput::make('display_name')
                    ->label('Label')
                    ->maxLength(255),
                Forms\Components\TextInput::make('serial_number')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('mac_address')
                    ->label('MAC address')
                    ->maxLength(255)
                    ->helperText('Used for MAC binding on MikroTik / ONU.'),
                Forms\Components\TextInput::make('model')
                    ->maxLength(255),
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
                Forms\Components\TextInput::make('onu_external_id')
                    ->label('External / LOID ID')
                    ->maxLength(64),
                Forms\Components\Select::make('vendor')
                    ->options([
                        'huawei' => 'Huawei',
                        'zte' => 'ZTE',
                        'fiberhome' => 'Fiberhome',
                        'vsol' => 'VSOL',
                        'other' => 'Other',
                    ])
                    ->searchable(),
                Forms\Components\TextInput::make('management_ip')
                    ->label('Device IP')
                    ->maxLength(45),
                Forms\Components\TextInput::make('vlan_id')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(4094),
                Forms\Components\TextInput::make('framed_ip_address')
                    ->label('Static IP')
                    ->maxLength(45),
                Forms\Components\TextInput::make('rx_power_dbm')
                    ->label('RX (dBm)')
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        'in_stock' => 'In stock',
                        'assigned' => 'Assigned',
                        'faulty' => 'Faulty',
                    ])
                    ->required()
                    ->default('assigned'),
                Forms\Components\Section::make('Lease (rental ONU)')
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
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Section::make('Binding & auth')
                    ->schema([
                        Forms\Components\Toggle::make('mac_binding_strict')
                            ->label('Strict MAC binding'),
                        Forms\Components\Toggle::make('serial_binding_strict')
                            ->label('Strict serial binding'),
                        Forms\Components\TextInput::make('authorization_password')
                            ->label('ONU GPON password / LOID password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Encrypted. TR-069 / OMCI automation not connected yet.'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', '!=', 'olt'))
            ->columns([
                Tables\Columns\TextColumn::make('connection_type')
                    ->label('Connection')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mac_address')
                    ->label('MAC')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('framed_ip_address')
                    ->label('Static IP')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('mac_binding_strict')
                    ->label('MAC bind')
                    ->boolean(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Label')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('olt_label')
                    ->label('OLT')
                    ->getStateUsing(fn (Device $record): string => $record->olt?->adminLabel() ?? '—'),
                Tables\Columns\TextColumn::make('oltPort.label')
                    ->label('PON port')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('card_no')
                    ->label('Card')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('pon_no')
                    ->label('PON')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('onu_index')
                    ->label('ONU #')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('rx_power_dbm')
                    ->label('RX dBm')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).' dBm' : '—')
                    ->badge()
                    ->color(fn (Device $record): string => \App\Support\OnuSignalLevel::filamentColor(
                        \App\Support\OnuSignalLevel::classifyRx(
                            $record->rx_power_dbm !== null ? (float) $record->rx_power_dbm : null,
                            strtolower((string) ($record->onu_oper_status ?? '')),
                        ),
                    )),
                Tables\Columns\TextColumn::make('tx_power_dbm')
                    ->label('TX dBm')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2).' dBm' : '—')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('onu_oper_status')
                    ->label('ONU status')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['customer_id'] = $this->getOwnerRecord()->getKey();
                        $data['type'] = $data['type'] ?? 'onu';

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
