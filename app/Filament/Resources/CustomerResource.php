<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Filament\Resources\CustomerResource\Widgets;
use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Device;
use App\Services\Mikrotik\MikrotikServerService;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Support\MacAddress;
use App\Support\OnuSignalLevel;
use App\Support\OpticalThresholds;
use App\Services\Billing\BillingAccountListCounts;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'subscribers';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Subscribers';

    protected static ?string $navigationLabel = 'All accounts';

    protected static ?string $modelLabel = 'Subscriber';

    protected static ?string $pluralModelLabel = 'Subscribers';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    protected static int $globalSearchResultsLimit = 20;

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'customer_code',
            'phone',
            'email',
            'mikrotik_secret_name',
            'radius_username',
            'nid_number',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Customer $record */
        return ($record->customer_code ?: '#'.$record->id).' — '.$record->name;
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Customer $record */
        return array_filter([
            'Phone' => $record->phone,
            'PPP user' => $record->mikrotik_secret_name,
            'Status' => $record->status,
        ]);
    }

    public static function form(Form $form): Form
    {
        return \App\Filament\Forms\SubscriberFormSchema::configure($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Infolists\Components\Section::make('Account')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Infolists\Components\TextEntry::make('customer_code')->label('Subscriber code'),
                                Infolists\Components\TextEntry::make('name'),
                                Infolists\Components\TextEntry::make('phone')->copyable(),
                                Infolists\Components\TextEntry::make('email')->placeholder('—')->copyable(),
                                Infolists\Components\TextEntry::make('nid_number')->label('NID')->placeholder('—'),
                                Infolists\Components\TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                                Infolists\Components\TextEntry::make('area.name')->label('Area')->placeholder('—'),
                                Infolists\Components\TextEntry::make('zone.name')->label('Zone')->placeholder('—'),
                                Infolists\Components\TextEntry::make('subzone.name')->label('Sub zone')->placeholder('—'),
                                Infolists\Components\TextEntry::make('joined_at')->date()->placeholder('—'),
                                Infolists\Components\TextEntry::make('updated_at')->label('Last update')->dateTime()->placeholder('—'),
                                Infolists\Components\TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                        Infolists\Components\Section::make('Billing')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Infolists\Components\TextEntry::make('package.name')->label('Package'),
                                Infolists\Components\TextEntry::make('package.download_mbps')->label('Download (Mbps)')->placeholder('—'),
                                Infolists\Components\TextEntry::make('package.price_monthly')
                                    ->label('Plan price (monthly)')
                                    ->money('BDT')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('account_balance')
                                    ->label('Balance')
                                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' BDT'),
                                Infolists\Components\TextEntry::make('billing_mode')->badge(),
                                Infolists\Components\TextEntry::make('billing_day')->label('Billing day'),
                                Infolists\Components\TextEntry::make('grace_period_days')
                                    ->label('Grace period')
                                    ->formatStateUsing(fn ($state): string => 'Grace: '.(int) $state.' days'),
                                Infolists\Components\TextEntry::make('billing_status')
                                    ->label('Billing status')
                                    ->state(fn (Customer $record): string => $record->isServiceExpired() ? 'Expired' : 'Active')
                                    ->badge()
                                    ->color(fn (Customer $record): ?string => $record->isServiceExpired() ? 'danger' : 'success'),
                                Infolists\Components\TextEntry::make('service_expires_at')
                                    ->label('Valid until (শেষ বৈধ তারিখ)')
                                    ->date()
                                    ->placeholder('—')
                                    ->color(fn (Customer $record): ?string => $record->isServiceExpired() ? 'danger' : null),
                                Infolists\Components\TextEntry::make('service_off_date')
                                    ->label('Off হবে (লাইন বন্ধ)')
                                    ->state(fn (Customer $record): string => $record->serviceOffDate()?->format('d M Y') ?? '—')
                                    ->color(fn (Customer $record): ?string => $record->isServiceExpired() ? 'danger' : 'warning')
                                    ->helperText(fn (Customer $record): ?string => $record->service_expires_at
                                        ? $record->serviceExpirySummary()
                                        : 'Edit subscriber → Billing tab → Service valid until'),
                                Infolists\Components\TextEntry::make('reseller.name')->label('Reseller')->placeholder('—'),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                        Infolists\Components\Section::make('Network & line')
                            ->icon('heroicon-o-server-stack')
                            ->schema([
                                Infolists\Components\TextEntry::make('network_access_state')->badge(),
                                Infolists\Components\TextEntry::make('subscriber_type')
                                    ->label('Type')
                                    ->badge()
                                    ->formatStateUsing(fn (Customer $record): string => $record->subscriberTypeLabel())
                                    ->color(fn (Customer $record): string => $record->subscriberTypeColor()),
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn (Customer $record): string => $record->statusLabel())
                                    ->color(fn (Customer $record): string => $record->statusColor()),
                                Infolists\Components\TextEntry::make('connections_count')
                                    ->label('Connections')
                                    ->state(fn (Customer $record): int => $record->relationLoaded('devices')
                                        ? $record->devices->where('type', '!=', 'olt')->count()
                                        : $record->devices()->where('type', '!=', 'olt')->count())
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('radius_username')
                                    ->label('PPPoE / RADIUS username')
                                    ->placeholder('(uses subscriber code)')
                                    ->state(fn (Customer $record): string => filled($record->radius_username)
                                        ? (string) $record->radius_username
                                        : (string) $record->customer_code),
                                Infolists\Components\IconEntry::make('has_mikrotik_ppp_password')
                                    ->label('PPP password on file')
                                    ->boolean()
                                    ->state(fn (Customer $record): bool => filled($record->getAttributes()['mikrotik_ppp_password'] ?? null)),
                                Infolists\Components\TextEntry::make('ppp_caller_mac')
                                    ->label('PPP MAC (router)')
                                    ->state(function (Customer $record): ?string {
                                        $mac = $record->relationLoaded('activePppSession')
                                            ? $record->activePppSession?->caller_id
                                            : $record->activePppSession()->value('caller_id');

                                        return filled($mac) ? (string) $mac : null;
                                    })
                                    ->fontFamily('mono')
                                    ->placeholder('—')
                                    ->helperText('PPP/router MAC। ONU MAC আলাদা হলে Edit → ONU MAC ফিল্ডে OLT-এর MAC দিন (00AD24F0FB3C বা colon সহ)।'),
                                Infolists\Components\TextEntry::make('primary_cpe_mac')
                                    ->label('Primary CPE MAC')
                                    ->state(function (Customer $record): ?string {
                                        $sessionMac = $record->relationLoaded('activePppSession')
                                            ? $record->activePppSession?->caller_id
                                            : $record->activePppSession()->value('caller_id');
                                        if (filled($sessionMac)) {
                                            return MacAddress::normalizeColon((string) $sessionMac)
                                                ?? (string) $sessionMac;
                                        }

                                        $devices = $record->relationLoaded('devices')
                                            ? $record->devices
                                            : $record->devices()->where('type', '!=', 'olt')->get();

                                        return $devices->firstWhere('mac_address', '!=', null)?->mac_address;
                                    })
                                    ->fontFamily('mono')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('primary_cpe_ip')
                                    ->label('Framed IP')
                                    ->state(function (Customer $record): ?string {
                                        $sessionIp = $record->relationLoaded('activePppSession')
                                            ? $record->activePppSession?->framed_ip
                                            : $record->activePppSession()->value('framed_ip');

                                        if (filled($sessionIp)) {
                                            return (string) $sessionIp;
                                        }

                                        $devices = $record->relationLoaded('devices')
                                            ? $record->devices
                                            : $record->devices()->where('type', '!=', 'olt')->get();

                                        return $devices->firstWhere('framed_ip_address', '!=', null)?->framed_ip_address;
                                    })
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('onu_rx_dbm')
                                    ->label('Laser RX (dBm)')
                                    ->state(function (Customer $record): string {
                                        $onu = $record->primaryOnu()?->loadMissing('onuHealthScore');
                                        $rx = $onu?->onuHealthScore?->smoothed_rx_dbm ?? $onu?->rx_power_dbm;
                                        if ($rx === null) {
                                            return '—';
                                        }
                                        $line = number_format((float) $rx, 2).' dBm';
                                        if (OpticalThresholds::isHighRx((float) $rx)) {
                                            $line .= ' · Laser high (>' . number_format(OpticalThresholds::rxHighWarnAbove(), 1) . ')';
                                        }

                                        return $line;
                                    })
                                    ->badge()
                                    ->color(function (Customer $record): string {
                                        $onu = $record->primaryOnu();
                                        if ($onu === null) {
                                            return 'gray';
                                        }

                                        $rx = $onu->onuHealthScore?->smoothed_rx_dbm ?? $onu->rx_power_dbm;

                                        return OnuSignalLevel::filamentColor(OnuSignalLevel::classifyRx(
                                            $rx !== null ? (float) $rx : null,
                                            strtolower((string) ($onu->onu_oper_status ?? '')),
                                        ));
                                    }),
                                Infolists\Components\TextEntry::make('onu_fiber_health')
                                    ->label('Fiber health / stability')
                                    ->state(function (Customer $record): string {
                                        $health = $record->primaryOnu()?->onuHealthScore;
                                        if ($health === null) {
                                            return '—';
                                        }

                                        return sprintf(
                                            'Health %d%% · Stability %d%%',
                                            (int) ($health->fiber_health_score ?? $health->health_score ?? 0),
                                            (int) ($health->stability_score ?? 0),
                                        );
                                    }),
                                Infolists\Components\TextEntry::make('onu_tx_dbm')
                                    ->label('Laser TX (dBm)')
                                    ->state(function (Customer $record): string {
                                        $onu = $record->primaryOnu();
                                        if ($onu?->tx_power_dbm === null) {
                                            return '—';
                                        }
                                        $tx = (float) $onu->tx_power_dbm;
                                        $line = number_format($tx, 2).' dBm';
                                        if (OpticalThresholds::isHighTx($tx)) {
                                            $line .= ' · Laser high';
                                        }

                                        return $line;
                                    })
                                    ->badge()
                                    ->color(function (Customer $record): string {
                                        $onu = $record->primaryOnu();
                                        if ($onu === null) {
                                            return 'gray';
                                        }

                                        return OnuSignalLevel::filamentColor(OnuSignalLevel::classifyTx(
                                            $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null,
                                        ));
                                    }),
                                Infolists\Components\TextEntry::make('onu_laser_fix_hint')
                                    ->label('Laser / fiber action')
                                    ->state(function (Customer $record): string {
                                        $laser = OpticalThresholds::laserStatusForOnu($record->primaryOnu());

                                        return $laser['fix_hint'] ?? '—';
                                    })
                                    ->visible(fn (Customer $record): bool => filled(OpticalThresholds::laserStatusForOnu($record->primaryOnu())['fix_hint']))
                                    ->color('warning')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('onu_signal_level')
                                    ->label('Laser signal')
                                    ->state(function (Customer $record): string {
                                        $diag = app(CustomerOnuAutoProvisionService::class)
                                            ->opticalLinkDiagnostics($record);
                                        if ($diag['onu'] === null) {
                                            return Str::limit((string) ($diag['hint'] ?? 'No ONU linked'), 120);
                                        }
                                        $laser = OpticalThresholds::laserStatusForOnu($diag['onu']);
                                        if ($laser['rx'] === null && $laser['tx'] === null) {
                                            return 'Awaiting OLT optical sync';
                                        }

                                        return $laser['rx_label'] . ($laser['tx'] !== null ? ' · TX: ' . $laser['tx_label'] : '');
                                    })
                                    ->badge()
                                    ->color(function (Customer $record): string {
                                        $onu = $record->primaryOnu();
                                        if ($onu === null) {
                                            return 'gray';
                                        }

                                        return OnuSignalLevel::filamentColor(OnuSignalLevel::classifyRx(
                                            $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null,
                                            strtolower((string) ($onu->onu_oper_status ?? '')),
                                        ));
                                    }),
                                Infolists\Components\TextEntry::make('onu_status')
                                    ->label('Line / ONU status')
                                    ->state(function (Customer $record): string {
                                        $onu = $record->primaryOnu();
                                        if ($onu === null) {
                                            return 'সংযুক্ত নেই';
                                        }

                                        if ($onu->rx_power_dbm === null && in_array(strtolower((string) $onu->onu_oper_status), ['unknown', ''], true)) {
                                            return 'Awaiting OLT sync';
                                        }

                                        return ucfirst((string) ($onu->onu_oper_status ?? 'unknown'));
                                    })
                                    ->badge()
                                    ->color(function (Customer $record): string {
                                        $onu = $record->primaryOnu();
                                        if ($onu === null) {
                                            return 'gray';
                                        }

                                        return in_array(strtolower((string) $onu->onu_oper_status), ['online', 'active', 'up'], true)
                                            ? 'success'
                                            : 'warning';
                                    }),
                                Infolists\Components\TextEntry::make('onu_last_polled')
                                    ->label('Optical updated')
                                    ->state(fn (Customer $record): ?string => $record->primaryOnu()?->last_polled_at?->diffForHumans())
                                    ->placeholder('—'),
                            ])
                            ->columnSpan(['default' => 1, 'lg' => 1]),
                    ]),
                Infolists\Components\Section::make('KYC & documents')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Infolists\Components\TextEntry::make('kyc_status')->badge(),
                        Infolists\Components\TextEntry::make('kyc_verified_at')->dateTime()->placeholder('—'),
                        Infolists\Components\TextEntry::make('kyc_notes')->placeholder('—')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('segment')->placeholder('—'),
                        Infolists\Components\ImageEntry::make('photo_path')
                            ->label('Photo')
                            ->disk('local')
                            ->visibility('private')
                            ->placeholder('—')
                            ->columnSpan(1),
                        Infolists\Components\ImageEntry::make('nid_front_path')
                            ->label('NID front')
                            ->disk('local')
                            ->visibility('private')
                            ->placeholder('—')
                            ->columnSpan(1),
                        Infolists\Components\ImageEntry::make('nid_back_path')
                            ->label('NID back')
                            ->disk('local')
                            ->visibility('private')
                            ->placeholder('—')
                            ->columnSpan(1),
                        Infolists\Components\TextEntry::make('documents_count')
                            ->label('Uploaded files')
                            ->state(fn (Customer $record): int => (int) ($record->documents_count ?? $record->documents()->count()))
                            ->badge(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Contact numbers')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('contacts')
                            ->schema([
                                Infolists\Components\TextEntry::make('label')->badge(),
                                Infolists\Components\TextEntry::make('phone')->copyable(),
                                Infolists\Components\IconEntry::make('is_primary')->boolean()->label('Primary'),
                            ])
                            ->columns(3)
                            ->placeholder('No extra numbers — primary phone only.'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Subscriber')
                    ->searchable(['name', 'customer_code', 'phone', 'email'])
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->wrap()
                    ->description(fn (Customer $record): string => collect([
                        $record->customer_code,
                        $record->phone,
                        $record->package?->name,
                    ])->filter()->implode(' · ')),
                Tables\Columns\IconColumn::make('is_ppp_online')
                    ->label('Online')
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Customer $record): string => $record->ppp_last_seen_at
                        ? 'Last seen: '.$record->ppp_last_seen_at->diffForHumans()
                        : 'Not online (run Bandwidth → Sync now)'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Customer $record): string => $record->statusLabel())
                    ->color(fn (Customer $record): string => $record->statusColor())
                    ->searchable(),
                Tables\Columns\TextColumn::make('subscriber_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (Customer $record): string => $record->subscriberTypeLabel())
                    ->color(fn (Customer $record): string => $record->subscriberTypeColor()),
                Tables\Columns\TextColumn::make('package.name')
                    ->label('Package')
                    ->limit(24)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_balance')
                    ->label('Balance')
                    ->money('BDT')
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('network_access_state')
                    ->label('Line')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'suspended',
                    ]),
                Tables\Columns\TextColumn::make('service_expires_at')
                    ->label('Valid until')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (Customer $record): ?string => $record->isServiceExpired() ? 'danger' : null),
                Tables\Columns\TextColumn::make('customer_code')
                    ->label('Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kyc_status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'gray' => 'review',
                        'success' => 'verified',
                        'danger' => 'rejected',
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('area.name')
                    ->label('Area')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subzone.name')
                    ->label('Subzone')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reseller.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('mikrotik_secret_name')
                    ->label('PPP user')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('onu_rx_dbm')
                    ->label('ONU RX')
                    ->state(function (Customer $record): string {
                        $onu = $record->primaryOnu();
                        if ($onu?->rx_power_dbm === null) {
                            return $onu ? 'No data' : '—';
                        }

                        return number_format((float) $onu->rx_power_dbm, 1).' dBm';
                    })
                    ->badge()
                    ->color(function (Customer $record): string {
                        $onu = $record->primaryOnu();
                        if ($onu === null) {
                            return 'gray';
                        }

                        return OnuSignalLevel::filamentColor(OnuSignalLevel::classifyRx(
                            $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null,
                            strtolower((string) ($onu->onu_oper_status ?? '')),
                        ));
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('connections_count')
                    ->label('Connections')
                    ->getStateUsing(fn (Customer $record): int => (int) ($record->connections_count ?? 0))
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('joined_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginationPageOptions([15, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No subscribers yet')
            ->emptyStateDescription('Add a subscriber or import PPP secrets from MikroTik.')
            ->emptyStateIcon('heroicon-o-users')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(CustomerStatus::options()),
                Tables\Filters\SelectFilter::make('subscriber_type')
                    ->label('Type')
                    ->options(SubscriberType::options()),
                Tables\Filters\Filter::make('free_clients')
                    ->label('Free only')
                    ->toggle()
                    ->query(fn ($query) => $query->where('subscriber_type', SubscriberType::FREE)),
                Tables\Filters\Filter::make('vip_clients')
                    ->label('VIP only')
                    ->toggle()
                    ->query(fn ($query) => $query->where('subscriber_type', SubscriberType::VIP)),
                Tables\Filters\Filter::make('service_expired')
                    ->label('Service expired')
                    ->toggle()
                    ->query(fn ($query) => $query->whereNotNull('service_expires_at')->whereDate('service_expires_at', '<', now()->toDateString())),
                Tables\Filters\Filter::make('service_active_window')
                    ->label('Expiring within 7 days')
                    ->toggle()
                    ->query(fn ($query) => $query->whereNotNull('service_expires_at')
                        ->whereDate('service_expires_at', '>=', now()->toDateString())
                        ->whereDate('service_expires_at', '<=', now()->addDays(7)->toDateString())),
                Tables\Filters\SelectFilter::make('kyc_status')
                    ->options([
                        'pending' => 'Pending',
                        'review' => 'Review',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('segment')
                    ->options(fn (): array => Cache::remember(
                        'customer_segments:'.(\App\Support\TenantResolver::currentTenantId() ?? 0),
                        300,
                        fn (): array => Customer::query()
                            ->whereNotNull('segment')
                            ->distinct()
                            ->orderBy('segment')
                            ->pluck('segment', 'segment')
                            ->all(),
                    )),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 4])
            ->deferFilters()
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('verify_kyc')
                    ->label('Verify KYC')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Customer $record): bool => $record->kyc_status !== 'verified')
                    ->requiresConfirmation()
                    ->action(function (Customer $record): void {
                        $record->update([
                            'kyc_status' => 'verified',
                            'kyc_verified_at' => now(),
                        ]);
                        Notification::make()->title('KYC marked verified')->success()->send();
                    }),
                Tables\Actions\Action::make('set_suspended')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn (Customer $record): bool => $record->status !== CustomerStatus::SUSPENDED)
                    ->requiresConfirmation()
                    ->action(fn (Customer $record) => $record->update(['status' => CustomerStatus::SUSPENDED])),
                Tables\Actions\Action::make('set_terminated')
                    ->label('Terminate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Customer $record): bool => $record->status !== CustomerStatus::TERMINATED)
                    ->requiresConfirmation()
                    ->action(fn (Customer $record) => $record->update([
                        'status' => CustomerStatus::TERMINATED,
                        'network_access_state' => 'suspended',
                    ])),
                Tables\Actions\Action::make('renew_service')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\TextInput::make('days')
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->maxValue(730)
                            ->required(),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $days = (int) ($data['days'] ?? 30);
                        $base = $record->service_expires_at && $record->service_expires_at->isFuture()
                            ? $record->service_expires_at->copy()
                            : now()->startOfDay();
                        $record->forceFill([
                            'service_expires_at' => $base->addDays($days)->toDateString(),
                            'status' => 'active',
                            'network_access_state' => 'active',
                        ])->save();
                        Notification::make()
                            ->title('Service renewed')
                            ->body('New valid-until: '.$record->fresh()?->service_expires_at?->toDateString())
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('network_off')
                    ->label('Net OFF')
                    ->icon('heroicon-o-signal-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Customer $record): void {
                        $record->update(['network_access_state' => 'suspended']);
                        SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
                        Notification::make()->title('Network suspended')->success()->send();
                    }),
                Tables\Actions\Action::make('network_on')
                    ->label('Net ON')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Customer $record): void {
                        $record->update([
                            'status' => 'active',
                            'network_access_state' => 'active',
                        ]);
                        SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
                        Notification::make()->title('Network active')->success()->send();
                    }),
                Tables\Actions\Action::make('kick_mikrotik_sessions')
                    ->label('Kick PPP')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Customer $record): void {
                        $n = app(MikrotikServerService::class)->kickPppoeActiveSessionsForCustomer($record);
                        Notification::make()
                            ->title('MikroTik PPP kick')
                            ->body("Sessions removed: {$n}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('link_onu_quick')
                    ->label('Link ONU')
                    ->icon('heroicon-o-light-bulb')
                    ->color('info')
                    ->modalHeading(fn (Customer $r): string => 'ONU সংযুক্ত করুন — '.$r->name)
                    ->modalDescription('OLT inventory থেকে ONU EPON port বেছে নিন। RX/TX signal অনুযায়ী সাজানো।')
                    ->form([
                        Forms\Components\Select::make('onu_device_id')
                            ->label('ONU (EPON/GPON port)')
                            ->placeholder('EPON port বা serial খুঁজুন...')
                            ->searchable()
                            ->required()
                            ->options(function (Customer $record): array {
                                return Device::query()
                                    ->withoutGlobalScopes()
                                    ->where('tenant_id', $record->tenant_id)
                                    ->where('type', 'onu')
                                    ->where(function ($q) use ($record): void {
                                        $q->whereNull('customer_id')
                                            ->orWhere('customer_id', $record->id);
                                    })
                                    ->orderByDesc('rx_power_dbm')
                                    ->limit(400)
                                    ->get()
                                    ->mapWithKeys(fn (Device $onu): array => [
                                        $onu->id => trim(sprintf(
                                            '%s · %s · RX %s',
                                            $onu->display_name ?: 'ONU',
                                            $onu->serial_number ?: $onu->mac_address ?: '—',
                                            $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : 'no data',
                                        )),
                                    ])
                                    ->all();
                            }),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $onu = Device::query()
                            ->withoutGlobalScopes()
                            ->where('tenant_id', $record->tenant_id)
                            ->where('type', 'onu')
                            ->find($data['onu_device_id']);

                        if ($onu === null) {
                            Notification::make()->title('ONU not found')->danger()->send();

                            return;
                        }

                        Device::query()
                            ->withoutGlobalScopes()
                            ->where('customer_id', $record->id)
                            ->where('type', 'onu')
                            ->where('id', '!=', $onu->id)
                            ->update(['customer_id' => null]);

                        app(CustomerOnuAutoProvisionService::class)->assignOnuToCustomer($record, (int) $onu->id, 'manual', 100);

                        Notification::make()
                            ->title('ONU linked ✓')
                            ->body(sprintf('%s · RX %s · TX %s',
                                $onu->display_name,
                                $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : '—',
                                $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2).' dBm' : '—',
                            ))
                            ->success()
                            ->send();
                    }),
                ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->button(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_active')
                        ->label('Set active')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => static::bulkUpdateStatus($records, CustomerStatus::ACTIVE)),
                    Tables\Actions\BulkAction::make('bulk_suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => static::bulkUpdateStatus($records, CustomerStatus::SUSPENDED)),
                    Tables\Actions\BulkAction::make('bulk_terminate')
                        ->label('Mark left / terminated')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn ($records) => static::bulkUpdateStatus($records, CustomerStatus::TERMINATED)),
                    Tables\Actions\BulkAction::make('bulk_free')
                        ->label('Set as Free (no bill)')
                        ->icon('heroicon-o-gift')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(fn ($records) => static::bulkUpdateSubscriberType($records, SubscriberType::FREE)),
                    Tables\Actions\BulkAction::make('bulk_vip')
                        ->label('Set as VIP (no auto off)')
                        ->icon('heroicon-o-star')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn ($records) => static::bulkUpdateSubscriberType($records, SubscriberType::VIP)),
                    Tables\Actions\BulkAction::make('bulk_standard')
                        ->label('Set as Standard')
                        ->requiresConfirmation()
                        ->action(fn ($records) => static::bulkUpdateSubscriberType($records, SubscriberType::STANDARD)),
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $result = app(\App\Services\Subscribers\CustomerDeletionService::class)
                                ->deleteMany($records);

                            if ($result['deleted'] > 0) {
                                Notification::make()
                                    ->title('Deleted '.$result['deleted'].' subscriber(s)')
                                    ->success()
                                    ->send();
                            }

                            if ($result['failed'] !== []) {
                                $first = $result['failed'][0];
                                Notification::make()
                                    ->title('Some subscribers could not be deleted')
                                    ->body(($first['code'] ?? '#'.$first['id']).': '.$first['error'])
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function clientsDirectoryTable(Table $table): Table
    {
        return static::table($table)
            ->columns(static::clientsDirectoryColumns())
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100, 200])
            ->emptyStateHeading('No clients found')
            ->emptyStateDescription('Add a client, change the tab preset, or clear filters.')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(CustomerStatus::options()),
                Tables\Filters\SelectFilter::make('package_id')
                    ->label('Package')
                    ->relationship('package', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('mikrotik_server_id')
                    ->label('Router')
                    ->relationship('mikrotikServer', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('area_id')
                    ->label('Area')
                    ->relationship('area', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('zone_id')
                    ->label('Zone')
                    ->relationship('zone', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['default' => 2, 'sm' => 3, 'lg' => 5]);
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected static function clientsDirectoryColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('customer_code')
                ->label('C.ID')
                ->searchable()
                ->sortable()
                ->fontFamily('mono'),
            Tables\Columns\ImageColumn::make('photo_path')
                ->label('Photo')
                ->disk('local')
                ->visibility('private')
                ->circular()
                ->height(36)
                ->width(36)
                ->defaultImageUrl(fn (): string => 'https://ui-avatars.com/api/?background=0d9488&color=fff&name=C'),
            Tables\Columns\TextColumn::make('name')
                ->label('Name')
                ->searchable(['name', 'phone', 'email'])
                ->sortable()
                ->weight(FontWeight::SemiBold)
                ->description(fn (Customer $record): ?string => $record->phone),
            Tables\Columns\TextColumn::make('mikrotik_secret_name')
                ->label('PPPoE ID')
                ->searchable()
                ->fontFamily('mono')
                ->placeholder('—')
                ->copyable(),
            Tables\Columns\TextColumn::make('package.name')
                ->label('Package')
                ->sortable()
                ->limit(24)
                ->placeholder('—'),
            Tables\Columns\TextColumn::make('account_balance')
                ->label('Balance')
                ->money('BDT')
                ->sortable()
                ->alignEnd(),
            Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (Customer $record): string => $record->statusLabel())
                ->color(fn (Customer $record): string => $record->statusColor())
                ->sortable(),
            Tables\Columns\TextColumn::make('coverage')
                ->label('Area / Zone')
                ->state(fn (Customer $record): string => collect([
                    $record->area?->name,
                    $record->zone?->name,
                ])->filter()->implode(' · ') ?: '—')
                ->wrap(),
            Tables\Columns\IconColumn::make('is_ppp_online')
                ->label('Online')
                ->boolean()
                ->trueIcon('heroicon-o-signal')
                ->falseIcon('heroicon-o-signal-slash')
                ->trueColor('success')
                ->falseColor('gray')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $records
     */
    public static function bulkUpdateSubscriberType($records, string $type): void
    {
        $type = SubscriberType::normalize($type);
        foreach ($records as $record) {
            $record->update(['subscriber_type' => $type]);
            SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
        }
        Notification::make()
            ->title('Updated '.count($records).' subscriber type(s) → '.SubscriberType::label($type))
            ->success()
            ->send();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $records
     */
    public static function bulkUpdateStatus($records, string $status): void
    {
        foreach ($records as $record) {
            $network = match ($status) {
                CustomerStatus::ACTIVE => 'active',
                CustomerStatus::SUSPENDED, CustomerStatus::EXPIRED, CustomerStatus::TERMINATED => 'suspended',
                default => $record->network_access_state,
            };
            $record->update([
                'status' => $status,
                'network_access_state' => $network,
            ]);
            SyncCustomerNetworkAccessJob::dispatch((int) $record->tenant_id, (int) $record->id)->afterResponse();
        }
        Notification::make()
            ->title('Updated '.count($records).' subscriber(s)')
            ->success()
            ->send();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'package:id,name,download_mbps,price_monthly',
                'area:id,name',
                'zone:id,name',
                'subzone:id,name',
                'reseller:id,name',
                'onuDevice' => fn ($query) => $query->select([
                    'devices.id',
                    'devices.customer_id',
                    'devices.type',
                    'devices.rx_power_dbm',
                    'devices.onu_oper_status',
                    'devices.display_name',
                ]),
            ])
            ->withCount([
                'devices as connections_count' => fn (Builder $query): Builder => $query->where('devices.type', '!=', 'olt'),
            ]);
    }

    protected static function permissionPrefix(): string
    {
        return 'customers';
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\SubscriberLiveTrafficWidget::class,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public static function onuInventoryOptions(): array
    {
        $tenantId = \App\Support\TenantResolver::currentTenantId();
        if ($tenantId === null) {
            return [];
        }

        return Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->orderBy('display_name')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Device $onu): array => [
                $onu->id => trim(sprintf(
                    '%s · %s · RX %s',
                    $onu->display_name ?: 'ONU',
                    $onu->mac_address ?: '—',
                    $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 1).' dBm' : '—',
                )),
            ])
            ->all();
    }

    /**
     * Custom billing account lists — Filament skips registration when {@see $shouldRegisterNavigation} is false.
     */
    public static function registerNavigationItems(): void
    {
        // Curated sidebar: ClientsSidebarRegistry (see ClientsSidebarNavigation).
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\DevicesRelationManager::class,
            RelationManagers\PppSessionsRelationManager::class,
            RelationManagers\SupportTicketsRelationManager::class,
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'active' => Pages\ListActiveCustomers::route('/active'),
            'today' => Pages\ListTodaysCustomers::route('/today'),
            'expire-3' => Pages\ListExpireIn3DaysCustomers::route('/expire-3'),
            'expire-7' => Pages\ListExpireIn7DaysCustomers::route('/expire-7'),
            'pending' => Pages\ListPendingCustomers::route('/pending'),
            'free' => Pages\ListFreeCustomers::route('/free'),
            'vip' => Pages\ListVipCustomers::route('/vip'),
            'expired' => Pages\ListExpiredCustomers::route('/expired'),
            'suspended' => Pages\ListSuspendedCustomers::route('/suspended'),
            'left' => Pages\ListLeftCustomers::route('/left'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
