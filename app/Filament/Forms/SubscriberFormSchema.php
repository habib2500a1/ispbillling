<?php

namespace App\Filament\Forms;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Package;
use App\Models\User;
use App\Support\BillingCycleType;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use App\Filament\Resources\CustomerResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;

final class SubscriberFormSchema
{
    /** @return array<string, int|string> */
    private static function grid(int $lg = 3, int $sm = 2): array
    {
        return ['default' => 1, 'sm' => $sm, 'lg' => $lg];
    }

    /** @return array<string, int|string> */
    private static function gridFour(): array
    {
        return ['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4];
    }

    /** @return array<string, int|string> */
    private static function gridTwo(): array
    {
        return ['default' => 1, 'lg' => 2];
    }

    public static function configure(Form $form): Form
    {
        return $form
            ->extraAttributes(['class' => 'isp-subscriber-form'])
            ->schema([
                Forms\Components\Tabs::make('subscriber_tabs')
                    ->extraAttributes(['class' => 'isp-subscriber-tabs'])
                    ->persistTabInQueryString('tab')
                    ->activeTab(1)
                    ->tabs([
                        self::essentialsTab(),
                        self::billingDatesTab(),
                        self::packageTab(),
                        self::networkTab(),
                        self::locationStaffTab(),
                        self::installationTab(),
                        self::notificationsPortalTab(),
                        self::kycTab(),
                        self::notesTagsTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function essentialsTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Essentials')
            ->icon('heroicon-o-star')
            ->badge('Required')
            ->schema([
                Forms\Components\Section::make('Must-have')
                    ->description('Name · phone · package · dates · PPP login · status')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Customer name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['default' => 'full', 'lg' => 2]),
                        Forms\Components\TextInput::make('phone')
                            ->label('Phone number')
                            ->tel()
                            ->required()
                            ->maxLength(32)
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? CustomerContact::normalizePhone($state) : null)
                            ->rules([
                                fn (?Customer $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record): void {
                                    if (blank($value)) {
                                        return;
                                    }
                                    $phone = CustomerContact::normalizePhone((string) $value);
                                    $exists = Customer::query()
                                        ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                        ->where('phone', $phone)
                                        ->exists();
                                    if ($exists) {
                                        $fail('This phone number is already registered.');
                                    }
                                },
                            ])
                            ->afterStateUpdated(function (?string $state, Forms\Set $set, Get $get): void {
                                if (blank($get('mikrotik_secret_name')) && filled($state)) {
                                    $digits = preg_replace('/\D+/', '', $state) ?? '';
                                    if ($digits !== '') {
                                        $set('mikrotik_secret_name', $digits);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('package_id')
                            ->label('Package')
                            ->relationship('package', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->native(false)
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get): void {
                                if (! $state || filled($get('service_expires_at'))) {
                                    return;
                                }
                                $set('service_expires_at', now()->addMonth()->endOfMonth()->toDateString());
                            }),
                        Forms\Components\Select::make('status')
                            ->options(CustomerStatus::options())
                            ->required()
                            ->default(CustomerStatus::ACTIVE)
                            ->native(false),
                        Forms\Components\DatePicker::make('joined_at')
                            ->label('Activation date')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y'),
                        Forms\Components\DatePicker::make('service_expires_at')
                            ->label('Expire date (valid until)')
                            ->native(false)
                            ->displayFormat('d M Y')
                            ->closeOnDateSelection()
                            ->helperText('Last day of service. Line off from the next day if expiry enforcement is on.'),
                        Forms\Components\TextInput::make('billing_day')
                            ->label('Bill generate day')
                            ->required()
                            ->numeric()
                            ->default(now()->day)
                            ->minValue(1)
                            ->maxValue(28)
                            ->helperText('Day of month for isp:generate-bills.'),
                        Forms\Components\TextInput::make('mikrotik_secret_name')
                            ->label('PPPoE username')
                            ->maxLength(128)
                            ->required()
                            ->helperText('Router secret / login. Auto-filled from phone if empty.'),
                        Forms\Components\TextInput::make('customer_code')
                            ->label('Customer ID')
                            ->maxLength(255)
                            ->helperText('Leave empty for auto-generated subscriber code.')
                            ->rules([
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                    if (filled($value) && ! \App\Support\CustomerCodeGenerator::isValidManualCode((string) $value)) {
                                        $fail('Invalid code for current format.');
                                    }
                                },
                            ])
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(self::grid()),
                Forms\Components\Section::make('Quick preview')
                    ->schema([
                        Forms\Components\Placeholder::make('essentials_preview')
                            ->label('')
                            ->content(fn (Get $get): HtmlString => self::essentialsPreview($get))
                            ->columnSpanFull(),
                    ])
                    ->collapsed(false),
            ]);
    }

    private static function billingDatesTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Billing & expire')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Forms\Components\Section::make('Billing mode & cycle')
                    ->schema([
                        Forms\Components\Select::make('billing_mode')
                            ->label('Billing type')
                            ->options([
                                'postpaid' => 'Postpaid',
                                'prepaid' => 'Prepaid',
                                'advance' => 'Advance / hybrid',
                            ])
                            ->default('postpaid')
                            ->required()
                            ->native(false)
                            ->live(),
                        Forms\Components\Select::make('subscriber_type')
                            ->label('Billing category')
                            ->options(SubscriberType::options())
                            ->default(SubscriberType::STANDARD)
                            ->required()
                            ->native(false),
                        Forms\Components\Placeholder::make('billing_cycle_preview')
                            ->label('Billing cycle')
                            ->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'cycle')),
                        Forms\Components\Placeholder::make('monthly_charge_preview')
                            ->label('Monthly charge')
                            ->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'price')),
                        Forms\Components\Placeholder::make('vat_preview')
                            ->label('VAT / tax')
                            ->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'vat')),
                    ])
                    ->columns(self::grid()),
                Forms\Components\Section::make('Dates')
                    ->schema([
                        Forms\Components\DatePicker::make('joined_at')
                            ->label('Billing start / activation')
                            ->native(false)
                            ->displayFormat('d M Y'),
                        Forms\Components\Placeholder::make('next_billing_preview')
                            ->label('Next billing date')
                            ->content(fn (Get $get): HtmlString => self::nextBillingPreview($get)),
                        Forms\Components\Placeholder::make('due_date_preview')
                            ->label('Typical due date')
                            ->content(fn (Get $get): HtmlString => self::dueDatePreview($get)),
                        Forms\Components\DatePicker::make('service_expires_at')
                            ->label('Expire date')
                            ->native(false)
                            ->displayFormat('d M Y'),
                        Forms\Components\TextInput::make('grace_period_days')
                            ->label('Grace days')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(90)
                            ->live(),
                        Forms\Components\Placeholder::make('grace_expire_preview')
                            ->label('Grace expire date')
                            ->content(fn (Get $get): HtmlString => self::graceExpirePreview($get)),
                        Forms\Components\Placeholder::make('last_paid_preview')
                            ->label('Last paid month')
                            ->content(fn (?Customer $record): HtmlString => self::lastPaidPreview($record))
                            ->visible(fn (?Customer $record): bool => $record !== null),
                    ])
                    ->columns(self::grid()),
                Forms\Components\Section::make('Amounts & limits')
                    ->schema([
                        Forms\Components\TextInput::make('account_balance')
                            ->label('Advance / wallet balance (BDT)')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('credit_limit')
                            ->label('Due limit / credit limit (BDT)')
                            ->numeric()
                            ->helperText('Max open invoice balance. Empty = no limit.'),
                        Forms\Components\TextInput::make('meta.discount_note')
                            ->label('Discount note')
                            ->maxLength(255)
                            ->helperText('Fixed discount is applied via coupons on invoices.'),
                        Forms\Components\TextInput::make('security_deposit_required')
                            ->label('Security deposit required')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('security_deposit_collected')
                            ->label('Security deposit collected')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('late_fee_fixed')
                            ->label('Late fee fixed (BDT)')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('late_fee_percent')
                            ->label('Late fee %')
                            ->numeric()
                            ->default(0),
                        Forms\Components\Select::make('late_fee_period')
                            ->options(['daily' => 'Daily', 'weekly' => 'Weekly'])
                            ->default('daily')
                            ->native(false),
                        Forms\Components\TextInput::make('reconnection_fee_amount')
                            ->label('Reconnection fee (BDT)')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(self::grid())
                    ->collapsible(),
            ]);
    }

    private static function packageTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Package')
            ->icon('heroicon-o-signal')
            ->schema([
                Forms\Components\Section::make('Package selection')
                    ->schema([
                        Forms\Components\Select::make('package_id')
                            ->relationship('package', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->columnSpan(['default' => 'full', 'lg' => 2]),
                        Forms\Components\Select::make('pending_package_id')
                            ->label('Scheduled package change')
                            ->relationship('pendingPackage', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\DatePicker::make('pending_package_effective_date')
                            ->label('Change effective date')
                            ->native(false),
                    ])
                    ->columns(self::grid()),
                Forms\Components\Section::make('Plan details (from package)')
                    ->description('Speed, FUP and MikroTik profile come from the selected package.')
                    ->schema([
                        Forms\Components\Placeholder::make('pkg_name')->label('Package name')->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'name')),
                        Forms\Components\Placeholder::make('pkg_down')->label('Download speed')->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'down')),
                        Forms\Components\Placeholder::make('pkg_up')->label('Upload speed')->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'up')),
                        Forms\Components\Placeholder::make('pkg_fup')->label('FUP / data cap')->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'fup')),
                        Forms\Components\Placeholder::make('pkg_profile')->label('MikroTik profile')->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'profile')),
                        Forms\Components\Placeholder::make('pkg_setup')->label('Setup fee')->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'setup')),
                    ])
                    ->columns(self::grid()),
            ]);
    }

    private static function networkTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('MikroTik & ONU')
            ->icon('heroicon-o-server-stack')
            ->schema([
                Forms\Components\Section::make('MikroTik / PPPoE')
                    ->schema([
                        Forms\Components\Select::make('mikrotik_server_id')
                            ->label('Router')
                            ->relationship('mikrotikServer', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\TextInput::make('mikrotik_secret_name')
                            ->label('PPPoE username')
                            ->maxLength(128),
                        Forms\Components\TextInput::make('mikrotik_ppp_password')
                            ->label('PPPoE password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Forms\Components\TextInput::make('radius_username')
                            ->label('RADIUS username')
                            ->maxLength(255),
                        Forms\Components\Select::make('network_access_state')
                            ->label('Line status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false),
                        Forms\Components\Placeholder::make('queue_preview')
                            ->label('Queue / profile')
                            ->content(fn (Get $get): HtmlString => self::packageFieldPreview($get, 'profile'))
                            ->columnSpan(['default' => 'full', 'lg' => 2]),
                        Forms\Components\TextInput::make('meta.static_ip')
                            ->label('Static IP (if any)')
                            ->maxLength(45),
                        Forms\Components\TextInput::make('meta.mac_binding')
                            ->label('MAC binding (router / CPE)')
                            ->placeholder('00:AD:24:F0:FB:3C')
                            ->maxLength(32)
                            ->helperText('MikroTik PPP MAC (auto)। ONU auto চাইলে MikroTik secret comment-এ EPON0/4:29 বা ONU MAC লিখুন।'),
                        Forms\Components\TextInput::make('meta.vlan')
                            ->label('VLAN')
                            ->maxLength(32),
                    ])
                    ->columns(self::grid()),
                Forms\Components\Section::make('ONU / GPON')
                    ->schema([
                        Forms\Components\TextInput::make('meta.epon_port')
                            ->label('EPON port (auto link)')
                            ->placeholder('EPON0/4:29')
                            ->maxLength(32)
                            ->helperText('OLT-এর port লেবেল — save-এ auto ONU link + dBm।'),
                        Forms\Components\TextInput::make('meta.onu_mac')
                            ->label('ONU MAC (OLT থেকে auto)')
                            ->placeholder('00AD24F0FB3C বা 00:AD:24:F0:FB:3C')
                            ->maxLength(32)
                            ->helperText('ONU স্টিকার/OLT MAC (router MAC নয়)। save-এ auto sync + link।'),
                        Forms\Components\Select::make('onu_device_pick')
                            ->label('ONU serial / inventory')
                            ->placeholder('Pick ONU from OLT inventory')
                            ->searchable()
                            ->options(fn (): array => CustomerResource::onuInventoryOptions())
                            ->dehydrated(false)
                            ->helperText('Link after save. RX/TX shown on subscriber view.'),
                        Forms\Components\Placeholder::make('onu_signal_preview')
                            ->label('ONU signal')
                            ->content(fn (?Customer $record): HtmlString => self::onuSignalPreview($record))
                            ->visible(fn (?Customer $record): bool => $record !== null),
                    ])
                    ->columns(self::gridTwo()),
                Forms\Components\Section::make('ONU billing')
                    ->schema([
                        Forms\Components\TextInput::make('meta.onu_rent')->label('ONU rent (BDT/mo)')->numeric()->default(0),
                        Forms\Components\TextInput::make('meta.onu_installment')->label('ONU installment (BDT)')->numeric()->default(0),
                        Forms\Components\TextInput::make('meta.onu_deposit')->label('ONU deposit (BDT)')->numeric()->default(0),
                        Forms\Components\TextInput::make('meta.router_rent')->label('Router rent (BDT/mo)')->numeric()->default(0),
                    ])
                    ->columns(self::gridFour())
                    ->collapsible(),
            ]);
    }

    private static function locationStaffTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Location & staff')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Forms\Components\Section::make('Address')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->label('Full address')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('area_id')
                            ->label('Area')
                            ->relationship('area', 'name')
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Select::make('zone_id')
                            ->label('Zone')
                            ->relationship('zone', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('subzone_id')
                            ->label('Sub zone')
                            ->relationship('subzone', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('meta.gps_lat')->label('GPS latitude')->maxLength(32),
                        Forms\Components\TextInput::make('meta.gps_lng')->label('GPS longitude')->maxLength(32),
                    ])
                    ->columns(self::grid()),
                Forms\Components\Section::make('Staff assignment')
                    ->schema([
                        Forms\Components\Select::make('meta.collector_id')
                            ->label('Collector')
                            ->options(fn (): array => self::tenantStaffOptions())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\Select::make('meta.technician_id')
                            ->label('Technician')
                            ->options(fn (): array => self::tenantStaffOptions())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\Select::make('meta.branch_id')
                            ->label('Branch')
                            ->options(fn (): array => Branch::query()
                                ->when($tid = TenantResolver::currentTenantId(), fn ($q) => $q->where('tenant_id', $tid))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false),
                        Forms\Components\Select::make('reseller_id')
                            ->label('Reseller')
                            ->relationship('reseller', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ])
                    ->columns(self::gridTwo()),
                Forms\Components\Section::make('Identity (extended)')
                    ->schema([
                        Forms\Components\TextInput::make('customer_code')
                            ->label('Customer ID')
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                        Forms\Components\TextInput::make('email')->email()->maxLength(255),
                        Forms\Components\TextInput::make('segment')->placeholder('residential, corporate, VIP'),
                        Forms\Components\Select::make('auto_suspend_override')
                            ->label('Auto suspend line')
                            ->options([
                                '' => 'Default',
                                '0' => 'Never auto off',
                                '1' => 'Allow auto off',
                            ])
                            ->nullable()
                            ->native(false)
                            ->dehydrateStateUsing(fn ($state) => $state === '' || $state === null ? null : (bool) (int) $state)
                            ->formatStateUsing(fn ($state) => $state === null ? '' : ((bool) $state ? '1' : '0')),
                    ])
                    ->columns(self::gridTwo())
                    ->collapsible(),
            ]);
    }

    private static function installationTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Installation')
            ->icon('heroicon-o-wrench-screwdriver')
            ->schema([
                Forms\Components\Section::make('Installation')
                    ->schema([
                        Forms\Components\DatePicker::make('meta.installation_date')
                            ->label('Installation date')
                            ->native(false),
                        Forms\Components\TextInput::make('meta.installation_charge')
                            ->label('Installation charge (BDT)')
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('meta.cable_length_m')
                            ->label('Cable length (m)')
                            ->numeric(),
                        Forms\Components\Select::make('meta.installation_status')
                            ->label('Installation status')
                            ->options([
                                'pending' => 'Pending',
                                'scheduled' => 'Scheduled',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->default('pending')
                            ->native(false),
                    ])
                    ->columns(self::gridTwo()),
            ]);
    }

    private static function notificationsPortalTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Portal & alerts')
            ->icon('heroicon-o-bell-alert')
            ->schema([
                Forms\Components\Section::make('Customer portal')
                    ->schema([
                        Forms\Components\TextInput::make('portal_password')
                            ->label('Portal password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make((string) $state) : null)
                            ->helperText('Login: subscriber code, phone, or email.'),
                        Forms\Components\Toggle::make('meta.portal_otp_login')
                            ->label('OTP login enabled')
                            ->default(false),
                        Forms\Components\Toggle::make('meta.portal_2fa')
                            ->label('2FA for portal')
                            ->default(false),
                    ])
                    ->columns(self::gridTwo()),
                Forms\Components\Section::make('Notification settings')
                    ->schema([
                        Forms\Components\Toggle::make('meta.notify_sms')->label('SMS alert')->default(true),
                        Forms\Components\Toggle::make('meta.notify_whatsapp')->label('WhatsApp alert')->default(false),
                        Forms\Components\Toggle::make('meta.notify_email')->label('Email alert')->default(false),
                        Forms\Components\Toggle::make('meta.notify_push')->label('Push notification')->default(false),
                    ])
                    ->columns(self::gridFour()),
                Forms\Components\Section::make('Auto features')
                    ->schema([
                        Forms\Components\Toggle::make('meta.auto_invoice')->label('Auto invoice generate')->default(true),
                        Forms\Components\Toggle::make('meta.auto_pppoe')->label('Auto PPPoE create on router')->default(true),
                        Forms\Components\Toggle::make('meta.auto_onu')->label('Auto ONU provision')->default(true),
                        Forms\Components\Toggle::make('meta.auto_activate')->label('Auto activate line')->default(true),
                        Forms\Components\Toggle::make('meta.auto_suspend')->label('Auto suspend when due')->default(true),
                        Forms\Components\Toggle::make('meta.auto_renew')->label('Auto renew prepaid')->default(false),
                    ])
                    ->columns(self::grid()),
            ]);
    }

    private static function kycTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('KYC')
            ->icon('heroicon-o-identification')
            ->schema([
                Forms\Components\Section::make('NID & verification')
                    ->schema([
                        Forms\Components\TextInput::make('nid_number')->label('NID number')->maxLength(255),
                        Forms\Components\Select::make('kyc_status')
                            ->options([
                                'pending' => 'Pending',
                                'review' => 'Under review',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required()
                            ->native(false),
                        Forms\Components\DateTimePicker::make('kyc_verified_at')->label('Verified at'),
                        Forms\Components\Textarea::make('kyc_notes')->rows(2)->columnSpanFull(),
                    ])
                    ->columns(self::gridTwo()),
                Forms\Components\Section::make('Documents')
                    ->schema([
                        Forms\Components\FileUpload::make('photo_path')
                            ->label('Customer photo')
                            ->image()
                            ->disk('local')
                            ->directory(fn (?Customer $record): string => 'subscribers/'.($record?->getKey() ?? 'draft'))
                            ->visibility('private')
                            ->maxSize(4096),
                        Forms\Components\FileUpload::make('nid_front_path')
                            ->label('NID front')
                            ->image()
                            ->disk('local')
                            ->directory(fn (?Customer $record): string => 'subscribers/'.($record?->getKey() ?? 'draft'))
                            ->visibility('private')
                            ->maxSize(4096),
                        Forms\Components\FileUpload::make('nid_back_path')
                            ->label('NID back')
                            ->image()
                            ->disk('local')
                            ->directory(fn (?Customer $record): string => 'subscribers/'.($record?->getKey() ?? 'draft'))
                            ->visibility('private')
                            ->maxSize(4096),
                    ])
                    ->columns(self::grid()),
            ]);
    }

    private static function notesTagsTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Notes & tags')
            ->icon('heroicon-o-pencil-square')
            ->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Internal notes')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Section::make('Tags')
                    ->schema([
                        Forms\Components\Toggle::make('meta.tag_vip')->label('VIP tag'),
                        Forms\Components\Toggle::make('meta.tag_gaming')->label('Gaming user'),
                        Forms\Components\Toggle::make('meta.tag_corporate')->label('Corporate user'),
                        Forms\Components\Toggle::make('meta.tag_late_payer')->label('Late payer tag'),
                    ])
                    ->columns(self::gridFour()),
                Forms\Components\Section::make('Additional contacts')
                    ->schema([
                        Forms\Components\Repeater::make('contacts')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('label')
                                    ->options([
                                        'mobile' => 'Mobile',
                                        'home' => 'Home',
                                        'office' => 'Office',
                                        'whatsapp' => 'WhatsApp',
                                        'emergency' => 'Emergency',
                                    ])
                                    ->default('mobile')
                                    ->required(),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? CustomerContact::normalizePhone($state) : null),
                                Forms\Components\Toggle::make('is_primary'),
                                Forms\Components\Toggle::make('is_whatsapp'),
                            ])
                            ->columns(self::gridFour())
                            ->defaultItems(0)
                            ->addActionLabel('Add number')
                            ->collapsible(),
                    ]),
            ]);
    }

    /**
     * @return array<int|string, string>
     */
    private static function tenantStaffOptions(): array
    {
        $tenantId = TenantResolver::currentTenantId();
        if ($tenantId === null) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function resolvePackage(Get $get): ?Package
    {
        $id = $get('package_id');
        if (! $id) {
            return null;
        }

        return Package::query()->find($id);
    }

    private static function essentialsPreview(Get $get): HtmlString
    {
        $pkg = self::resolvePackage($get);
        $expires = $get('service_expires_at');
        $grace = (int) ($get('grace_period_days') ?? 10);

        $lines = [
            '<strong>Package:</strong> '.($pkg?->name ?? '—'),
            '<strong>Monthly:</strong> '.($pkg ? number_format((float) $pkg->price_monthly, 2).' BDT' : '—'),
            '<strong>PPP user:</strong> '.($get('mikrotik_secret_name') ?: '—'),
            '<strong>Expire:</strong> '.($expires ? Carbon::parse($expires)->format('d M Y') : '—'),
            '<strong>Grace expire:</strong> '.self::graceExpirePreview($get)->toHtml(),
        ];

        return new HtmlString('<div class="isp-sub-preview">'.implode(' · ', $lines).'</div>');
    }

    private static function packageFieldPreview(Get $get, string $field): HtmlString
    {
        $pkg = self::resolvePackage($get);
        if (! $pkg) {
            return new HtmlString('<span class="isp-sub-muted">Select a package</span>');
        }

        $text = match ($field) {
            'name' => $pkg->name,
            'cycle' => BillingCycleType::label($pkg->billing_cycle_type ?? BillingCycleType::MONTHLY),
            'price' => number_format((float) $pkg->price_monthly, 2).' BDT / month',
            'vat' => number_format((float) $pkg->vat_percent, 1).'% VAT · SD '.number_format((float) $pkg->sd_percent, 1).'%',
            'down' => ($pkg->download_mbps ?? '—').' Mbps',
            'up' => ($pkg->upload_mbps ?? '—').' Mbps',
            'fup' => $pkg->included_data_gb
                ? number_format((float) $pkg->included_data_gb, 1).' GB included'
                : 'Unlimited / plan default',
            'profile' => $pkg->mikrotik_profile_name ?: '—',
            'setup' => number_format((float) $pkg->setup_fee, 2).' BDT',
            default => '—',
        };

        return new HtmlString('<strong>'.e($text).'</strong>');
    }

    private static function nextBillingPreview(Get $get): HtmlString
    {
        $day = max(1, min(28, (int) ($get('billing_day') ?? 1)));
        $next = now()->day > $day
            ? now()->addMonth()->day($day)
            : now()->day($day);

        return new HtmlString('<strong>'.$next->format('d M Y').'</strong>');
    }

    private static function dueDatePreview(Get $get): HtmlString
    {
        $grace = (int) ($get('grace_period_days') ?? 10);
        $day = max(1, min(28, (int) ($get('billing_day') ?? 1)));
        $bill = now()->day > $day ? now()->addMonth()->day($day) : now()->day($day);
        $due = $bill->copy()->addDays($grace);

        return new HtmlString('<strong>'.$due->format('d M Y').'</strong> <span class="isp-sub-muted">(bill day + grace)</span>');
    }

    private static function graceExpirePreview(Get $get): HtmlString
    {
        $expires = $get('service_expires_at');
        if (! $expires) {
            return new HtmlString('<span class="isp-sub-muted">Set expire date</span>');
        }
        $grace = (int) ($get('grace_period_days') ?? 0);
        $date = Carbon::parse($expires)->addDays($grace);

        return new HtmlString('<strong>'.$date->format('d M Y').'</strong>');
    }

    private static function lastPaidPreview(?Customer $record): HtmlString
    {
        if ($record === null) {
            return new HtmlString('—');
        }
        $payment = $record->payments()
            ->where('status', 'completed')
            ->orderByDesc('paid_at')
            ->first();
        if ($payment === null) {
            return new HtmlString('<span class="isp-sub-muted">No payments yet</span>');
        }

        return new HtmlString('<strong>'.($payment->paid_at?->format('M Y') ?? '—').'</strong> · '.$payment->paid_at?->format('d M Y'));
    }

    private static function onuSignalPreview(?Customer $record): HtmlString
    {
        if ($record === null) {
            return new HtmlString('—');
        }
        $onu = $record->devices()->where('type', 'onu')->first();
        if ($onu === null) {
            return new HtmlString('<span class="isp-sub-muted">No ONU linked</span>');
        }

        return new HtmlString(sprintf(
            '<strong>%s</strong> · RX %s · TX %s',
            e($onu->serial_number ?? $onu->display_name ?? 'ONU'),
            $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 1).' dBm' : '—',
            $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 1).' dBm' : '—',
        ));
    }
}
