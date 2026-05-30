<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Support\BkashSettings;
use App\Support\PaymentAdminAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;

/**
 * @property Form $form
 */
class ManagePaymentSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.manage-payment-settings';

    protected static ?string $slug = 'payment-gateway-settings';

    protected static ?string $navigationLabel = 'bKash & gateways';

    protected static ?string $title = 'Payment gateway settings';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /** Active merchant gateway tab — synced to ?gateway= (survives Livewire save). */
    #[Url(as: 'gateway', except: 'piprapay')]
    public string $activeGatewayTab = 'piprapay';

    public static function canAccess(): bool
    {
        return PaymentAdminAccess::canManageGateways();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $gateway = request()->query('gateway', 'piprapay');
        $this->activeGatewayTab = $this->normalizeGatewayTab(is_string($gateway) ? $gateway : 'piprapay');

        if ($gateway === 'bkash' && request()->query('merchant') === '1') {
            $this->redirect(ManageBkashMerchantSettings::getUrl());

            return;
        }

        if ($gateway === 'bkash' && request()->query('merchant') !== '1') {
            $this->redirect(ManagePersonalMfsSettings::getUrl(['tab' => 'bkash']));

            return;
        }

        if ($gateway === 'nagad' && request()->query('merchant') !== '1') {
            $this->redirect(ManagePersonalMfsSettings::getUrl(['tab' => 'nagad']));

            return;
        }

        $this->fillBkashForm();
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Tabs::make('gateways')
                            ->activeTab(fn (): int => match ($this->activeGatewayTab) {
                                'bkash' => 2,
                                'nagad' => 3,
                                'sslcommerz' => 4,
                                'rocket' => 5,
                                default => 1,
                            })
                            ->persistTabInQueryString('gateway')
                            ->tabs([
                                Tab::make('PipraPay')
                                    ->id('piprapay')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema($this->pipraPayFormSchema()),
                                Tab::make('bKash Merchant API')
                                    ->id('bkash')
                                    ->icon('heroicon-o-building-storefront')
                                    ->schema($this->bkashFormSchema()),
                                Tab::make('Nagad Merchant API')
                                    ->id('nagad')
                                    ->icon('heroicon-o-building-library')
                                    ->schema($this->nagadFormSchema()),
                                Tab::make('SSLCommerz')
                                    ->id('sslcommerz')
                                    ->icon('heroicon-o-credit-card')
                                    ->schema($this->sslCommerzFormSchema()),
                                Tab::make('Rocket')
                                    ->id('rocket')
                                    ->icon('heroicon-o-device-phone-mobile')
                                    ->schema([
                                        \Filament\Forms\Components\Toggle::make('rocket_enabled')
                                            ->label('Enable Rocket on /pay and portal'),
                                        TextInput::make('rocket_merchant_number')
                                            ->label('Merchant Rocket number')
                                            ->tel()
                                            ->maxLength(20)
                                            ->placeholder('01XXXXXXXXX'),
                                        TextInput::make('rocket_merchant_name')
                                            ->label('Display name')
                                            ->maxLength(64),
                                        Textarea::make('rocket_instructions')
                                            ->label('Extra instructions')
                                            ->rows(2)
                                            ->columnSpanFull(),
                                        \Filament\Forms\Components\Toggle::make('rocket_auto_verify')
                                            ->label('Auto-approve valid TrxID (else queue for admin)')
                                            ->columnSpanFull(),
                                        TextInput::make('rocket_verify_url')
                                            ->label('Optional verify URL (POST)')
                                            ->url()
                                            ->nullable()
                                            ->columnSpanFull(),
                                        Placeholder::make('rocket_webhook_hint')
                                            ->label('Webhook')
                                            ->content('POST /api/webhooks/payments/rocket with secret ROCKET_WEBHOOK_SECRET'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function pipraPayFormSchema(): array
    {
        return [
            Section::make('PipraPay checkout (v3)')->schema([
                \Filament\Forms\Components\Toggle::make('piprapay_enabled')->label('Enable PipraPay on /pay and portal'),
                Select::make('piprapay_api_mode')
                    ->label('API mode')
                    ->options([
                        'redirect' => 'v3 — /checkout/redirect (recommended)',
                        'legacy' => 'Legacy — /create-charge',
                    ])
                    ->default('redirect'),
                TextInput::make('piprapay_api_key')
                    ->label('API key (MHS-PIPRAPAY-API-KEY)')
                    ->password()
                    ->revealable()
                    ->dehydrated(false)
                    ->helperText('Brand Settings → Api Settings. Leave blank to keep saved key.'),
                TextInput::make('piprapay_base_url')
                    ->label('API base URL')
                    ->placeholder('https://pay.flixbd.xyz/api')
                    ->helperText('Panel “Base URL” — must end with /api. Checkout: …/checkout/redirect · Verify: …/verify-payment')
                    ->columnSpanFull(),
                TextInput::make('piprapay_public_url')
                    ->label('Public URL for PipraPay callbacks')
                    ->placeholder('http://isp.flixbd.xyz or http://72-18-215-205.sslip.io')
                    ->helperText('Cannot use bare IP (72.18.215.205). Use a domain that points to this server.')
                    ->columnSpanFull(),
                Placeholder::make('piprapay_domain_hint')
                    ->label('PipraPay → Domains (whitelist this host)')
                    ->content(fn ($get): string => 'Add: '.(parse_url((string) ($get('piprapay_public_url') ?: config('piprapay.public_url')), PHP_URL_HOST) ?: '?').' — NOT the raw IP.')
                    ->columnSpanFull(),
                Placeholder::make('piprapay_webhook_hint')
                    ->label('Webhook URL (IPN)')
                    ->content('POST '.rtrim((string) config('piprapay.public_url', config('app.url')), '/').'/piprapay/webhook — header: MHS-PIPRAPAY-API-KEY')
                    ->columnSpanFull(),
                Placeholder::make('piprapay_docs')
                    ->label('Documentation')
                    ->content('https://piprapay.readme.io/reference/redirect-checkout · https://piprapay.readme.io/reference/verify-payment')
                    ->columnSpanFull(),
            ])->columns(2),
        ];
    }

    protected function nagadFormSchema(): array
    {
        return [
            Section::make('Nagad merchant checkout (API)')
                ->description('Nagad Personal + SMS verify: Payments → bKash Personal / Nagad Personal')
                ->schema([
                \Filament\Forms\Components\Toggle::make('nagad_enabled')->label('Enable Nagad merchant checkout'),
                Radio::make('nagad_sandbox')
                    ->label('Environment')
                    ->options(['1' => 'Sandbox', '0' => 'Live'])
                    ->inline(),
                TextInput::make('nagad_merchant_id')->label('Merchant ID')->maxLength(64),
                TextInput::make('nagad_merchant_number')->label('Merchant number')->maxLength(32),
                Textarea::make('nagad_pg_public_key')
                    ->label('PG public key')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('nagad_merchant_private_key')
                    ->label('Merchant private key')
                    ->rows(3)
                    ->dehydrated(false)
                    ->helperText('Leave blank to keep saved key.')
                    ->columnSpanFull(),
                ])->columns(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function sslCommerzFormSchema(): array
    {
        return [
            Section::make('SSLCommerz')->schema([
                \Filament\Forms\Components\Toggle::make('sslcommerz_enabled')->label('Enable SSLCommerz'),
                Radio::make('sslcommerz_sandbox')
                    ->label('Environment')
                    ->options(['1' => 'Sandbox', '0' => 'Live'])
                    ->inline(),
                TextInput::make('sslcommerz_store_id')->label('Store ID')->maxLength(64),
                TextInput::make('sslcommerz_store_password')
                    ->label('Store password')
                    ->password()
                    ->revealable()
                    ->dehydrated(false)
                    ->helperText('Leave blank to keep saved password.'),
            ])->columns(2),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function bkashFormSchema(): array
    {
        return [
            Placeholder::make('bkash_personal_link')
                ->label('bKash Personal?')
                ->content(new \Illuminate\Support\HtmlString(
                    '<a class="text-primary-600 font-semibold underline" href="'
                    .\App\Filament\Pages\ManagePersonalMfsSettings::getUrl(['tab' => 'bkash'])
                    .'">Open bKash Personal / Nagad Personal</a> (Send Money + SMS — not this merchant API).'
                )),
            Placeholder::make('bkash_mode_status')
                ->label('Live status on /pay & portal')
                ->content(function (): \Illuminate\Support\HtmlString {
                    $s = BkashSettings::statusSummary();
                    $lines = [];
                    $lines[] = $s['personal']
                        ? '<span class="text-success-600">Personal: ON</span> ('.e($s['personal_number']).') — Send Money + TrxID'
                        : '<span class="text-gray-500">Personal: OFF</span>';
                    if ($s['merchant'] && $s['merchant_configured']) {
                        $lines[] = '<span class="text-success-600">Merchant API: ON</span> — '.e(BkashSettings::callbackUrl());
                    } elseif ($s['merchant']) {
                        $lines[] = '<span class="text-warning-600">Merchant API: enabled but credentials incomplete</span>';
                    } else {
                        $lines[] = '<span class="text-gray-500">Merchant API: OFF</span> — enable below and Save';
                    }

                    return new \Illuminate\Support\HtmlString(
                        '<div class="text-sm space-y-1">'.implode('<br>', $lines).'</div>'
                    );
                })
                ->columnSpanFull(),
            Section::make('bKash Tokenized API (merchant checkout)')
                ->description('Official bKash merchant API — callback URL must match bKash panel.')
                ->schema([
                    Radio::make('bkash_environment')
                        ->label('Sandbox / Live')
                        ->options([
                            BkashSettings::ENV_SANDBOX => 'Sandbox (test)',
                            BkashSettings::ENV_LIVE => 'Live (production)',
                        ])
                        ->inline()
                        ->live(),
                    Placeholder::make('bkash_base_url_preview')
                        ->label('API base URL (auto)')
                        ->content(fn ($get): string => BkashSettings::baseUrlForEnvironment(
                            (string) ($get('bkash_environment') ?? BkashSettings::ENV_SANDBOX)
                        )),
                    TextInput::make('bkash_app_key')
                        ->label('App key (X-APP-Key)')
                        ->maxLength(255),
                    TextInput::make('bkash_app_secret')
                        ->label('App secret')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText('Leave blank to keep the saved secret.'),
                    TextInput::make('bkash_username')
                        ->label('Username')
                        ->maxLength(255),
                    TextInput::make('bkash_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText('Leave blank to keep the saved password.'),
                    TextInput::make('bkash_http_timeout')
                        ->label('HTTP timeout (seconds)')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(120)
                        ->default(30),
                    TextInput::make('bkash_callback_url')
                        ->label('Callback URL (must match bKash merchant panel)')
                        ->maxLength(255)
                        ->helperText(BkashSettings::callbackUrlHint().' Your site: '.rtrim((string) config('app.url'), '/').'/bkash/callback')
                        ->placeholder(fn (): string => rtrim((string) config('app.url'), '/').'/bkash/callback'),
                ])
                ->columns(1),
            Section::make('Merchant API on /pay & portal')
                ->schema([
                    Radio::make('bkash_enabled')
                        ->label('bKash Merchant API')
                        ->options([
                            '1' => 'Enabled (official checkout redirect)',
                            '0' => 'Disabled',
                        ])
                        ->inline()
                        ->default('0')
                        ->helperText('Does not turn off bKash Personal (TrxID). Both can show as two buttons for customers.'),
                ]),
            Section::make('Activation & expiry')
                ->description('Optional. Outside this window, bKash checkout is hidden even if enabled.')
                ->schema([
                    DatePicker::make('bkash_activation_date')
                        ->label('Activation date')
                        ->native(false),
                    DatePicker::make('bkash_expiry_date')
                        ->label('Expiry date')
                        ->native(false)
                        ->after('bkash_activation_date'),
                ])
                ->columns(2),
            Section::make('Use this gateway on')
                ->schema([
                    CheckboxList::make('bkash_channels')
                        ->label('Applicable for')
                        ->options(BkashSettings::channelLabels())
                        ->columns(1)
                        ->default(BkashSettings::allChannels()),
                ]),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        $tab = $this->resolvedGatewayTab();

        return [
            Action::make('testBkash')
                ->label('Test bKash connection')
                ->color('gray')
                ->action('testBkashConnection')
                ->visible($tab === 'bkash'),
            Action::make('save')
                ->label(match ($tab) {
                    'piprapay' => 'Save PipraPay',
                    'bkash' => 'Save bKash Merchant API',
                    'nagad' => 'Save Nagad Merchant API',
                    'sslcommerz' => 'Save SSLCommerz',
                    'rocket' => 'Save Rocket',
                    default => 'Save',
                })
                ->action('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function testBkashConnection(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->data ?? [];
        $appKey = trim((string) ($state['bkash_app_key'] ?? ''));
        $username = trim((string) ($state['bkash_username'] ?? ''));
        $appSecret = trim((string) ($state['bkash_app_secret'] ?? ''));
        $password = trim((string) ($state['bkash_password'] ?? ''));

        if ($appKey !== '') {
            config(['bkash.app_key' => $appKey]);
        }
        if ($appSecret !== '') {
            config(['bkash.app_secret' => $appSecret]);
        }
        if ($username !== '') {
            config(['bkash.username' => $username]);
        }
        if ($password !== '') {
            config(['bkash.password' => $password]);
        }

        $environment = (string) ($state['bkash_environment'] ?? BkashSettings::ENV_SANDBOX);
        config([
            'bkash.environment' => $environment,
            'bkash.base_url' => BkashSettings::baseUrlForEnvironment($environment),
        ]);

        $result = BkashSettings::testConnection();

        $notification = Notification::make()
            ->title($result['ok'] ? 'bKash connection OK' : 'bKash connection failed')
            ->body($result['message']);

        $result['ok'] ? $notification->success() : $notification->danger();
        $notification->send();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $tab = $this->resolvedGatewayTab();
        $state = $this->data ?? [];

        $saved = match ($tab) {
            'piprapay' => $this->savePipraPaySettings($state),
            'bkash' => $this->saveBkashMerchantSettings($state),
            'nagad' => $this->saveNagadMerchantSettings($state),
            'sslcommerz' => $this->saveSslCommerzSettings($state),
            'rocket' => $this->saveRocketSettings($state),
            default => false,
        };

        if (! $saved) {
            return;
        }

        AppSetting::syncToRuntimeConfig();
        $this->fillBkashForm();
        $this->clearPasswordFieldsAfterSave($tab);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function savePipraPaySettings(array $state): bool
    {
        $enabled = $this->toBool($state['piprapay_enabled'] ?? false);
        $pipraKey = trim((string) ($state['piprapay_api_key'] ?? ''));
        $hasKey = $pipraKey !== '' || filled(AppSetting::getStoredValue('piprapay.api_key'));

        if ($enabled && ! $hasKey) {
            Notification::make()
                ->title('PipraPay API key required')
                ->body('Enable is ON: paste your MHS-PIPRAPAY-API-KEY from PipraPay Brand Settings, then Save again.')
                ->danger()
                ->send();

            return false;
        }

        $apiMode = (string) ($state['piprapay_api_mode'] ?? 'redirect');
        if (! in_array($apiMode, ['redirect', 'legacy'], true)) {
            $apiMode = 'redirect';
        }

        AppSetting::putValue('piprapay.enabled', $enabled ? '1' : '0');
        AppSetting::putValue('piprapay.api_mode', $apiMode);

        if ($pipraKey !== '') {
            AppSetting::putValue('piprapay.api_key', $pipraKey);
        }

        $pipraBase = trim((string) ($state['piprapay_base_url'] ?? ''));
        if ($pipraBase !== '') {
            AppSetting::putValue('piprapay.base_url', rtrim($pipraBase, '/'));
        } else {
            AppSetting::query()->where('key', 'piprapay.base_url')->delete();
            AppSetting::restoreConfigKeyFromEnv('piprapay.base_url');
        }

        $publicUrl = rtrim(trim((string) ($state['piprapay_public_url'] ?? '')), '/');
        if ($publicUrl !== '') {
            AppSetting::putValue('piprapay.public_url', $publicUrl);
        } else {
            AppSetting::query()->where('key', 'piprapay.public_url')->delete();
            AppSetting::restoreConfigKeyFromEnv('piprapay.public_url');
        }

        Notification::make()
            ->title('PipraPay saved')
            ->body(
                'PipraPay: '.($enabled ? 'ON' : 'OFF')
                .' · API key: '.(filled(config('piprapay.api_key')) ? 'saved' : 'missing')
                .' · Public URL: '.rtrim((string) config('piprapay.public_url', config('app.url')), '/')
            )
            ->success()
            ->send();

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveBkashMerchantSettings(array $state): bool
    {
        $merchantOn = $this->toBool($state['bkash_enabled'] ?? '0');
        $personalOn = BkashSettings::isPersonalEnabled();

        if ($merchantOn && ! $this->merchantCredentialsComplete($state)) {
            Notification::make()
                ->title('bKash Merchant API — credentials required')
                ->body('Merchant is ON: fill App key, App secret, Username, and Password (first time all four; later secret/password can stay blank if already saved).')
                ->danger()
                ->send();

            return false;
        }

        $environment = (string) ($state['bkash_environment'] ?? BkashSettings::ENV_SANDBOX);
        if (! in_array($environment, [BkashSettings::ENV_SANDBOX, BkashSettings::ENV_LIVE], true)) {
            $environment = BkashSettings::ENV_SANDBOX;
        }

        $before = $this->bkashSnapshot();

        AppSetting::putValue('bkash.merchant_enabled', $merchantOn ? '1' : '0');
        AppSetting::putValue('bkash.environment', $environment);
        AppSetting::putValue('bkash.base_url', BkashSettings::baseUrlForEnvironment($environment));

        $appKey = trim((string) ($state['bkash_app_key'] ?? ''));
        if ($appKey !== '') {
            AppSetting::putValue('bkash.app_key', $appKey);
        }

        $username = trim((string) ($state['bkash_username'] ?? ''));
        if ($username !== '') {
            AppSetting::putValue('bkash.username', $username);
        }

        AppSetting::putValue('bkash.http_timeout', (string) max(5, min(120, (int) ($state['bkash_http_timeout'] ?? 30))));

        $callbackUrl = rtrim(trim((string) ($state['bkash_callback_url'] ?? '')), '/');
        if ($callbackUrl !== '') {
            AppSetting::putValue('bkash.callback_url', $callbackUrl);
        } else {
            AppSetting::query()->where('key', 'bkash.callback_url')->delete();
            AppSetting::restoreConfigKeyFromEnv('bkash.callback_url');
        }

        $activation = $state['bkash_activation_date'] ?? null;
        if ($activation) {
            AppSetting::putValue('bkash.activation_date', $activation);
        } else {
            AppSetting::query()->where('key', 'bkash.activation_date')->delete();
            AppSetting::restoreConfigKeyFromEnv('bkash.activation_date');
        }

        $expiry = $state['bkash_expiry_date'] ?? null;
        if ($expiry) {
            AppSetting::putValue('bkash.expiry_date', $expiry);
        } else {
            AppSetting::query()->where('key', 'bkash.expiry_date')->delete();
            AppSetting::restoreConfigKeyFromEnv('bkash.expiry_date');
        }

        $channels = is_array($state['bkash_channels'] ?? null) ? $state['bkash_channels'] : [];
        if ($channels === []) {
            $channels = BkashSettings::enabledChannels() !== [] ? BkashSettings::enabledChannels() : BkashSettings::allChannels();
        }
        AppSetting::putValue('bkash.channels', BkashSettings::channelsToStorage($channels));

        $rawSecret = trim((string) ($state['bkash_app_secret'] ?? ''));
        if ($rawSecret !== '') {
            AppSetting::putValue('bkash.app_secret', $rawSecret);
        }

        $rawPass = trim((string) ($state['bkash_password'] ?? ''));
        if ($rawPass !== '') {
            AppSetting::putValue('bkash.password', $rawPass);
        }

        AppSetting::putValue('bkash.enabled', ($personalOn || $merchantOn) ? '1' : '0');

        $after = $this->bkashSnapshot();
        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Payment gateway (bKash merchant) updated',
                'context' => [
                    'diff' => $this->diffSnapshot($before, $after),
                    'secrets' => [
                        'bkash_app_secret_rotated' => $rawSecret !== '',
                        'bkash_password_rotated' => $rawPass !== '',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('payment_settings.audit_log_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        $summary = BkashSettings::statusSummary();

        Notification::make()
            ->title('bKash Merchant API saved')
            ->body(
                'Personal: '.($summary['personal'] ? 'ON' : 'OFF')
                .' · Merchant: '.($summary['merchant']
                    ? ($summary['merchant_configured'] ? 'ON (ready)' : 'ON (add credentials)')
                    : 'OFF')
            )
            ->success()
            ->send();

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveNagadMerchantSettings(array $state): bool
    {
        AppSetting::putValue('nagad.gateway_type', 'api');
        AppSetting::putValue('nagad.enabled', $this->toBool($state['nagad_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('nagad.sandbox', ($state['nagad_sandbox'] ?? '1') === '1' ? '1' : '0');
        AppSetting::putValue('nagad.merchant_id', trim((string) ($state['nagad_merchant_id'] ?? '')));
        AppSetting::putValue('nagad.merchant_number', trim((string) ($state['nagad_merchant_number'] ?? '')));
        AppSetting::putValue('nagad.pg_public_key', trim((string) ($state['nagad_pg_public_key'] ?? '')));

        $nagadPrivate = trim((string) ($state['nagad_merchant_private_key'] ?? ''));
        if ($nagadPrivate !== '') {
            AppSetting::putValue('nagad.merchant_private_key', $nagadPrivate);
        }

        Notification::make()
            ->title('Nagad Merchant API saved')
            ->success()
            ->send();

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveSslCommerzSettings(array $state): bool
    {
        AppSetting::putValue('sslcommerz.enabled', $this->toBool($state['sslcommerz_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('sslcommerz.sandbox', ($state['sslcommerz_sandbox'] ?? '1') === '1' ? '1' : '0');
        AppSetting::putValue('sslcommerz.store_id', trim((string) ($state['sslcommerz_store_id'] ?? '')));

        $sslPass = trim((string) ($state['sslcommerz_store_password'] ?? ''));
        if ($sslPass !== '') {
            AppSetting::putValue('sslcommerz.store_password', $sslPass);
        }

        Notification::make()
            ->title('SSLCommerz saved')
            ->success()
            ->send();

        return true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function saveRocketSettings(array $state): bool
    {
        AppSetting::putValue('rocket.enabled', $this->toBool($state['rocket_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('rocket.merchant_number', trim((string) ($state['rocket_merchant_number'] ?? '')));
        AppSetting::putValue('rocket.merchant_name', trim((string) ($state['rocket_merchant_name'] ?? '')));
        AppSetting::putValue('rocket.instructions', trim((string) ($state['rocket_instructions'] ?? '')));
        AppSetting::putValue('rocket.auto_verify', $this->toBool($state['rocket_auto_verify'] ?? false) ? '1' : '0');
        AppSetting::putValue('rocket.verify_url', trim((string) ($state['rocket_verify_url'] ?? '')));
        config([
            'payments.gateways.rocket.enabled' => $this->toBool($state['rocket_enabled'] ?? false),
        ]);

        Notification::make()
            ->title('Rocket saved')
            ->success()
            ->send();

        return true;
    }

    private function resolvedGatewayTab(): string
    {
        $queryTab = request()->query('gateway');
        if (is_string($queryTab) && $queryTab !== '') {
            $this->activeGatewayTab = $this->normalizeGatewayTab($queryTab);
        }

        return $this->normalizeGatewayTab($this->activeGatewayTab);
    }

    private function normalizeGatewayTab(string $tab): string
    {
        return in_array($tab, ['piprapay', 'bkash', 'nagad', 'sslcommerz', 'rocket'], true)
            ? $tab
            : 'piprapay';
    }

    private function clearPasswordFieldsAfterSave(string $tab): void
    {
        if ($tab === 'bkash') {
            $this->data['bkash_app_secret'] = '';
            $this->data['bkash_password'] = '';
        }
        if ($tab === 'piprapay') {
            $this->data['piprapay_api_key'] = '';
        }
        if ($tab === 'nagad') {
            $this->data['nagad_merchant_private_key'] = '';
        }
        if ($tab === 'sslcommerz') {
            $this->data['sslcommerz_store_password'] = '';
        }
    }

    private function fillBkashForm(): void
    {
        $environment = (string) config('bkash.environment', BkashSettings::ENV_SANDBOX);
        if (! in_array($environment, [BkashSettings::ENV_SANDBOX, BkashSettings::ENV_LIVE], true)) {
            $environment = BkashSettings::detectEnvironmentFromBaseUrl((string) config('bkash.base_url'));
        }

        $this->form->fill([
            'bkash_environment' => $environment,
            'bkash_app_key' => (string) (config('bkash.app_key') ?? ''),
            'bkash_app_secret' => '',
            'bkash_username' => (string) (config('bkash.username') ?? ''),
            'bkash_password' => '',
            'bkash_http_timeout' => (int) config('bkash.http_timeout', 30),
            'bkash_callback_url' => BkashSettings::callbackUrl(),
            'bkash_enabled' => BkashSettings::isMerchantEnabled() ? '1' : '0',
            'bkash_activation_date' => config('bkash.activation_date'),
            'bkash_expiry_date' => config('bkash.expiry_date'),
            'bkash_channels' => BkashSettings::enabledChannels(),
            'rocket_enabled' => (bool) config('rocket.enabled'),
            'rocket_merchant_number' => (string) config('rocket.merchant_number', ''),
            'rocket_merchant_name' => (string) config('rocket.merchant_name', ''),
            'rocket_instructions' => (string) config('rocket.instructions', ''),
            'rocket_auto_verify' => (bool) config('rocket.auto_verify'),
            'rocket_verify_url' => (string) config('rocket.verify_url', ''),
            'nagad_enabled' => (bool) (config('nagad.enabled') && (string) config('nagad.gateway_type') === 'api'),
            'nagad_sandbox' => config('nagad.sandbox') ? '1' : '0',
            'nagad_merchant_id' => (string) config('nagad.merchant_id', ''),
            'nagad_merchant_number' => (string) config('nagad.merchant_number', ''),
            'nagad_pg_public_key' => (string) config('nagad.pg_public_key', ''),
            'sslcommerz_enabled' => (bool) config('sslcommerz.enabled'),
            'sslcommerz_sandbox' => config('sslcommerz.sandbox') ? '1' : '0',
            'sslcommerz_store_id' => (string) config('sslcommerz.store_id', ''),
            'piprapay_enabled' => (bool) config('piprapay.enabled'),
            'piprapay_api_mode' => (string) config('piprapay.api_mode', 'redirect'),
            'piprapay_base_url' => (string) config('piprapay.base_url', ''),
            'piprapay_public_url' => (string) config('piprapay.public_url', config('app.url')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function merchantCredentialsComplete(array $state): bool
    {
        $appKey = trim((string) ($state['bkash_app_key'] ?? ''));
        $username = trim((string) ($state['bkash_username'] ?? ''));
        $newSecret = trim((string) ($this->data['bkash_app_secret'] ?? ''));
        $newPassword = trim((string) ($this->data['bkash_password'] ?? ''));

        return ($appKey !== '' || filled(config('bkash.app_key')))
            && ($newSecret !== '' || filled(AppSetting::getStoredValue('bkash.app_secret')))
            && ($username !== '' || filled(config('bkash.username')))
            && ($newPassword !== '' || filled(AppSetting::getStoredValue('bkash.password')));
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function bkashSnapshot(): array
    {
        return [
            'bkash.enabled' => (bool) config('bkash.enabled'),
            'bkash.environment' => (string) config('bkash.environment'),
            'bkash.base_url' => (string) config('bkash.base_url'),
            'bkash.app_key_set' => filled(config('bkash.app_key')),
            'bkash.username_set' => filled(config('bkash.username')),
            'bkash.channels' => BkashSettings::enabledChannels(),
            'bkash.activation_date' => config('bkash.activation_date'),
            'bkash.expiry_date' => config('bkash.expiry_date'),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffSnapshot(array $before, array $after): array
    {
        $diff = [];
        foreach ($before as $key => $from) {
            $to = $after[$key] ?? null;
            if ($from !== $to) {
                $diff[$key] = ['from' => $from, 'to' => $to];
            }
        }

        return $diff;
    }
}
