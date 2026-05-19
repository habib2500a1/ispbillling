<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OltResource\Pages;
use App\Filament\Resources\OltResource\RelationManagers;
use App\Models\Device;
use App\Services\Network\GponIntelligenceService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\OltSnmpProbeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OltResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'OLTs';

    protected static ?string $modelLabel = 'OLT';

    protected static ?string $pluralModelLabel = 'OLTs';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->olts();
    }

    public static function form(Form $form): Form
    {
        $driverOptions = collect(config('olt_drivers.drivers', []))
            ->mapWithKeys(fn (array $cfg, string $key): array => [$key => (string) ($cfg['label'] ?? $key)])
            ->all();

        return $form
            ->schema([
                Forms\Components\Hidden::make('type')->default('olt'),
                Forms\Components\Section::make('OLT manage')
                    ->description('পুরোনো প্যানেলের মতো: IP address, Community, OLT type। SNMP টেস্ট এখনও v2c sysDescr।')
                    ->schema([
                        Forms\Components\TextInput::make('management_ip')
                            ->label('IP address')
                            ->required()
                            ->maxLength(45)
                            ->placeholder('e.g. 103.29.127.90'),
                        Forms\Components\TextInput::make('snmp_community')
                            ->label('Community')
                            ->maxLength(255)
                            ->placeholder('public')
                            ->helperText('SNMP v2c read community (ওয়েব লগইন পাসওয়ার্ড নয়)। খালি রাখলে টেস্টে public।')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Forms\Components\Select::make('olt_driver')
                            ->label('OLT type')
                            ->options($driverOptions)
                            ->searchable()
                            ->required()
                            ->default('bdcom_epon')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($state === null || $state === '') {
                                    return;
                                }
                                $vendor = config("olt_drivers.drivers.{$state}.vendor");
                                if (is_string($vendor) && $vendor !== '') {
                                    $set('vendor', $vendor);
                                }
                            }),
                        Forms\Components\Select::make('status')
                            ->label('Run status')
                            ->options([
                                'active' => 'Active (চালু)',
                                'offline' => 'Offline',
                                'maintenance' => 'Maintenance',
                                'decommissioned' => 'Decommissioned',
                            ])
                            ->required()
                            ->default('active')
                            ->helperText('পুরোনো “Is active” চেকবক্সের জন্য Active বা Offline বেছে নিন।'),
                        Forms\Components\TextInput::make('display_name')
                            ->label('OLT name')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('পোর্টালে দেখানো নাম (খালি থাকলে সিরিয়াল ব্যবহার হয়)।'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Details')
                    ->schema([
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
                            ->helperText('OLT type বদলালে অটো ভরতে পারে; প্রয়োজনে হাতে ঠিক করুন।'),
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255)
                            ->helperText('Site or rack reference.'),
                        Forms\Components\TextInput::make('serial_number')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mac_address')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('model')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Forms\Components\Section::make('SNMP (v2c test) · SSH / Telnet (operator login)')
                    ->description(
                        'SNMP host খালি থাকলে উপরের IP address ব্যবহার হয়। Community উপরের সেকশনে দিন। SSH/Telnet: CLI লগইন (প্যানেলে রেকর্ড)।'
                    )
                    ->schema([
                        Forms\Components\TextInput::make('snmp_host')
                            ->label('SNMP host (override)')
                            ->maxLength(255)
                            ->helperText('Optional. Empty = use IP address above.'),
                        Forms\Components\TextInput::make('snmp_port')
                            ->label('SNMP port')
                            ->numeric()
                            ->default(161)
                            ->minValue(1)
                            ->maxValue(65535)
                            ->required(),
                        Forms\Components\TextInput::make('snmp_username')
                            ->label('SNMP v3 username (optional)')
                            ->maxLength(128)
                            ->helperText('শুধো রেকর্ড। প্যানেলের "Test SNMP" এখন শুধু v2c।'),
                        Forms\Components\Select::make('snmp_version')
                            ->options([
                                'v2c' => 'SNMP v2c',
                                'v3' => 'SNMP v3 (use external NMS until v3 is wired here)',
                            ])
                            ->default('v2c'),
                        Forms\Components\TextInput::make('telnet_port')
                            ->label('Telnet port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535),
                        Forms\Components\TextInput::make('ssh_port')
                            ->label('SSH port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535),
                        Forms\Components\TextInput::make('ssh_username')
                            ->label('SSH / Telnet username')
                            ->maxLength(64)
                            ->helperText('OLT CLI লগইন।'),
                        Forms\Components\TextInput::make('ssh_password')
                            ->label('SSH / Telnet password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('এডিটে খালি রাখলে আগের পাসওয়ার্ড থাকে।'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Section::make('OLT health snapshot (manual / polled JSON)')
                    ->schema([
                        Forms\Components\KeyValue::make('olt_health')
                            ->keyLabel('Metric')
                            ->valueLabel('Value')
                            ->helperText('Examples: cpu_percent, memory_percent, temperature_c, uptime — from your NMS or future poller.'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('meta')
                    ->label('Extra metadata')
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('olt_driver')
                    ->label('OLT type')
                    ->formatStateUsing(function (?string $state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        return (string) (config("olt_drivers.drivers.{$state}.label") ?? $state);
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vendor')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('management_ip')
                    ->label('Mgmt IP')
                    ->searchable(),
                Tables\Columns\TextColumn::make('onus_count')
                    ->counts('onus')
                    ->label('ONUs')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ports_count')
                    ->counts('ports')
                    ->label('PON ports')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('poll_intelligence')
                    ->label('SNMP poll')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->action(function (Device $record): void {
                        try {
                            $result = app(OltSnmpMonitorService::class)->pollOlt($record->fresh());
                            $sync = app(GponIntelligenceService::class)->syncAllOnuOpticalForOlt($record->fresh());
                            $body = $result['success']
                                ? "ONUs online: {$result['onus_online']}. Meta optical sync: {$sync['synced']}/{$sync['total']}."
                                : ($result['error'] ?? 'Unknown error');
                            if (! empty($result['bdcom_onu_discovered'])) {
                                $body .= " BDCOM: {$result['bdcom_onu_discovered']} ONUs ({$result['bdcom_onu_created']} new).";
                            }
                            $notification = Notification::make()
                                ->title($result['success'] ? 'SNMP poll OK' : 'SNMP poll failed')
                                ->body($body);
                            $result['success'] ? $notification->success() : $notification->danger();
                            $notification->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Poll error')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('test_snmp')
                    ->label('Test SNMP')
                    ->icon('heroicon-o-signal')
                    ->visible(fn (Device $record): bool => $record->type === 'olt')
                    ->action(function (Device $record): void {
                        if (! OltSnmpProbeService::isSnmpExtensionAvailable()) {
                            Notification::make()
                                ->title('SNMP টেস্ট চালানো যাচ্ছে না / SNMP test unavailable')
                                ->body(OltSnmpProbeService::installInstructions())
                                ->warning()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            $descr = app(OltSnmpProbeService::class)->fetchSysDescr($record->fresh());
                            Notification::make()
                                ->title('SNMP OK')
                                ->body(Str::limit($descr, 240))
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('SNMP test failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OnusRelationManager::class,
            RelationManagers\PortsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOlts::route('/'),
            'create' => Pages\CreateOlt::route('/create'),
            'edit' => Pages\EditOlt::route('/{record}/edit'),
        ];
    }
}
