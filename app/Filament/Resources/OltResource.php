<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\OltResource\Pages;
use App\Filament\Resources\OltResource\RelationManagers;
use App\Models\Device;
use App\Services\Network\GponIntelligenceService;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\OltSnmpProbeService;
use App\Filament\Pages\OltVpnManagementPage;
use App\Support\OltManagementHelper;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OltResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = Device::class;

    protected static function permissionPrefix(): string
    {
        return 'olts';
    }

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = 'OLTs';

    protected static ?string $modelLabel = 'OLT';

    protected static ?string $pluralModelLabel = 'OLTs';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function registerNavigationItems(): void
    {
        // Sidebar: {@see OltSidebarRegistry} under «OLT & Tools» only — never Inventory Pro / Network.
    }

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
                    ->description('পুরোনো প্যানেলের মতো: IP, Community, OLT type, Web login। SNMP = ONU sync; Web = Aveis UI।')
                    ->schema([
                        Forms\Components\TextInput::make('management_ip')
                            ->label('IP address')
                            ->required()
                            ->maxLength(45)
                            ->placeholder('103.29.127.94')
                            ->live(
                                onBlur: true,
                                condition: fn ($livewire): bool => $livewire instanceof CreateRecord,
                            )
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                $ip = OltManagementHelper::normalizeManagementIp($state);
                                if ($ip === null) {
                                    return;
                                }
                                $set('management_ip', $ip);
                                if (OltManagementHelper::isAveisDriver($get('olt_driver'))
                                    && blank($get('olt_web_url'))) {
                                    $set('olt_web_url', OltManagementHelper::defaultAveisWebUrl($ip));
                                }
                            }),
                        Forms\Components\TextInput::make('snmp_community')
                            ->label('Community')
                            ->maxLength(255)
                            ->placeholder('public')
                            ->default('public')
                            ->helperText('SNMP v2c (সাধারণত public) — ওয়েব পাসওয়ার্ড নয়।')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Forms\Components\Select::make('olt_driver')
                            ->label('OLT type')
                            ->options($driverOptions)
                            ->searchable()
                            ->required()
                            ->default('aveis_epon')
                            ->live(condition: fn ($livewire): bool => $livewire instanceof CreateRecord)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if ($state === null || $state === '') {
                                    return;
                                }
                                $vendor = config("olt_drivers.drivers.{$state}.vendor");
                                if (is_string($vendor) && $vendor !== '') {
                                    $set('vendor', $vendor);
                                }
                                if (OltManagementHelper::isAveisDriver($state)) {
                                    $ip = OltManagementHelper::normalizeManagementIp($get('management_ip'));
                                    if ($ip !== null && blank($get('olt_web_url'))) {
                                        $set('olt_web_url', OltManagementHelper::defaultAveisWebUrl($ip));
                                    }
                                    if (blank($get('snmp_community'))) {
                                        $set('snmp_community', 'public');
                                    }
                                }
                            }),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Is active')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\TextInput::make('display_name')
                            ->label('OLT name')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Aveis XE08'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Web UI (Aveis / VSOL / Ecom)')
                    ->description('OLT ওয়েব প্যানেল লগইন — লিংক “Open Web UI” থেকে খুলুন।')
                    ->schema([
                        Forms\Components\TextInput::make('olt_web_url')
                            ->label('Web IP')
                            ->maxLength(255)
                            ->placeholder('103.29.127.94:8506')
                            ->helperText('IP:port বা পুরো URL (যেমন http://103.29.127.94:8506/) — Aveis লগইন সাধারণত পোর্ট '.(int) config('olt_drivers.aveis_web_port', 8506).'. SNMP আলাদা।'),
                        Forms\Components\TextInput::make('olt_web_username')
                            ->label('Web username')
                            ->maxLength(64)
                            ->default('root')
                            ->placeholder('root'),
                        Forms\Components\TextInput::make('olt_web_password')
                            ->label('Web password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('এডিটে খালি = আগের পাসওয়ার্ড অপরিবর্তিত।'),
                    ])
                    ->columns(2)
                    ->visible(fn (Get $get): bool => OltManagementHelper::isAveisDriver($get('olt_driver'))
                        || OltManagementHelper::isConfigDrivenDriver($get('olt_driver'))),
                Forms\Components\Section::make('VPN — OLT private IP reach')
                    ->description('Save উপরে ডানে। **Test VPN** = ব্যাকগ্রাউন্ড (৫০৪ এড়াতে) → ৩০–৯০ সেক পর **VPN result (last)**।')
                    ->schema([
                        Forms\Components\View::make('vpn_guide')
                            ->view('filament.components.olt-vpn-guide')
                            ->viewData(function ($livewire): array {
                                $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                                $olt = $record instanceof Device ? $record : new Device(['meta' => []]);
                                $egress = trim((string) config('app.server_egress_ip', env('APP_SERVER_EGRESS_IP', '')));

                                return [
                                    'oltId' => $olt->id ?? '?',
                                    'vpnType' => OltManagementHelper::vpnType($olt),
                                    'hasOvpn' => OltManagementHelper::openVpnConfigPath($olt) !== null,
                                    'hasPptp' => OltManagementHelper::pptpConfigFromMeta($olt) !== null,
                                    'egressIp' => $egress !== '' ? $egress : '—',
                                    'vpnPageUrl' => OltVpnManagementPage::getUrl(),
                                ];
                            })
                            ->columnSpanFull()
                            ->visible(fn ($livewire): bool => method_exists($livewire, 'getRecord') && $livewire->getRecord() !== null),
                        Forms\Components\Select::make('olt_vpn_type')
                            ->label('VPN type')
                            ->options([
                                OltManagementHelper::VPN_NONE => 'None (direct only)',
                                OltManagementHelper::VPN_PPTP => 'PPTP (MikroTik)',
                                OltManagementHelper::VPN_OPENVPN => 'OpenVPN (.ovpn file)',
                            ])
                            ->default(OltManagementHelper::VPN_NONE)
                            ->live()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('olt_pptp_server')
                            ->label('PPTP server IP')
                            ->maxLength(45)
                            ->placeholder('103.29.127.228')
                            ->visible(fn (Get $get): bool => in_array($get('olt_vpn_type'), [OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN], true)),
                        Forms\Components\TextInput::make('olt_pptp_username')
                            ->label('PPTP username')
                            ->maxLength(64)
                            ->placeholder('ispbill')
                            ->visible(fn (Get $get): bool => in_array($get('olt_vpn_type'), [OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN], true)),
                        Forms\Components\TextInput::make('olt_pptp_password')
                            ->label('PPTP password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('এডিটে খালি = আগের পাসওয়ার্ড। OpenVPN থাকলেও PPTP তুলনার জন্য রাখা যায়।')
                            ->visible(fn (Get $get): bool => in_array($get('olt_vpn_type'), [OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN], true)),
                        Forms\Components\Placeholder::make('olt_openvpn_saved_hint')
                            ->label('')
                            ->content(function ($livewire): \Illuminate\Support\HtmlString {
                                $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                                if (! $record instanceof Device) {
                                    return new \Illuminate\Support\HtmlString('');
                                }
                                $path = OltManagementHelper::openVpnConfigPath($record);
                                if ($path === null) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-gray-500">.ovpn এখনো সেভ হয়নি — নিচে পেস্ট করে Save করুন।</span>');
                                }
                                $kb = round(filesize($path) / 1024, 1);

                                return new \Illuminate\Support\HtmlString(
                                    '<span class="font-medium text-success-600">✓ .ovpn সেভ আছে ('.$kb.' KB)</span> — বদলাতে নিচে নতুন কনফিগ পেস্ট করে Save।'
                                );
                            })
                            ->columnSpanFull()
                            ->visible(fn (Get $get, $livewire): bool => in_array($get('olt_vpn_type'), [OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN], true)
                                && method_exists($livewire, 'getRecord') && $livewire->getRecord() !== null),
                        Forms\Components\Textarea::make('olt_openvpn_config')
                            ->label('OpenVPN config (.ovpn) — পুরো ফাইল পেস্ট')
                            ->rows(12)
                            ->placeholder("client\ndev tun\nproto udp\nremote 103.29.127.12 1194\n...")
                            ->helperText('Notepad থেকে habib.ovpn খুলে Ctrl+A → Ctrl+C → এখানে Ctrl+V → VPN type OpenVPN → Save।')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => in_array($get('olt_vpn_type'), [OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN], true)),
                        Forms\Components\TextInput::make('olt_pptp_subnet')
                            ->label('Route subnet (CIDR)')
                            ->maxLength(64)
                            ->placeholder('103.29.127.0/24')
                            ->helperText('PPTP/OpenVPN দুটোতেই — খালি = OLT IP থেকে /24।')
                            ->visible(fn (Get $get): bool => in_array($get('olt_vpn_type'), [OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN], true)),
                    ])
                    ->columns(2)
                    ->collapsed(false),
                Forms\Components\Section::make('SNMP ONU OIDs (this OLT / model)')
                    ->description('যেকোনো মডেল — .env ছাড়াও শুধু এই OLT-এর জন্য OID দিন। খালি = global VSOL_SNMP_ONU_* বা vendor .env।')
                    ->schema([
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.desc')->label('Description OID')->placeholder('1.3.6.…'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.mac')->label('MAC OID'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.sn')->label('Serial OID'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.rx')->label('RX power OID'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.tx')->label('TX power OID'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.temp')->label('Temperature OID'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.voltage')->label('Voltage OID'),
                        Forms\Components\TextInput::make('meta.snmp_onu_oids.status')->label('Status OID'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (Get $get): bool => OltManagementHelper::isConfigDrivenDriver($get('olt_driver'))),
                Forms\Components\Section::make('Run status (advanced)')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'offline' => 'Offline',
                                'maintenance' => 'Maintenance',
                                'decommissioned' => 'Decommissioned',
                            ])
                            ->required()
                            ->default('active'),
                    ])
                    ->collapsed()
                    ->columns(2),
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Select::make('vendor')
                            ->options([
                                'huawei' => 'Huawei',
                                'zte' => 'ZTE',
                                'fiberhome' => 'Fiberhome',
                                'aveis' => 'Aveis',
                                'vsol' => 'VSOL',
                                'ecom' => 'Ecom',
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
                            ->maxLength(255)
                            ->helperText('খালি রাখলে IP থেকে auto (যেমন OLT-103-29-127-90)।'),
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
                Forms\Components\Section::make('Extra metadata')
                    ->schema([
                        Forms\Components\KeyValue::make('meta_extra')
                            ->label('Extra metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->helperText('শুধু টেক্সট মান। Aveis OLT-এ এই ফিল্ড বন্ধ — SNMP column map sync থেকে থাকে।'),
                    ])
                    ->visible(fn (Get $get): bool => ! OltManagementHelper::isAveisDriver($get('olt_driver')))
                    ->collapsible()
                    ->collapsed()
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
                Tables\Columns\TextColumn::make('vpn_type')
                    ->label('VPN')
                    ->badge()
                    ->state(fn (Device $record): string => OltManagementHelper::vpnType($record))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        OltManagementHelper::VPN_PPTP => 'PPTP',
                        OltManagementHelper::VPN_OPENVPN => 'OpenVPN',
                        default => '—',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        OltManagementHelper::VPN_PPTP, OltManagementHelper::VPN_OPENVPN => 'info',
                        default => 'gray',
                    }),
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
                            if (! empty($result['huawei_onu_discovered'])) {
                                $body .= " Huawei: {$result['huawei_onu_discovered']} ONUs ({$result['huawei_onu_created']} new).";
                            }
                            if (! empty($result['aveis_onu_discovered'])) {
                                $body .= " Aveis: {$result['aveis_onu_discovered']} ONUs ({$result['aveis_onu_created']} new).";
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
