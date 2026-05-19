<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Support\BkashSettings;
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

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

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
                            ->tabs([
                                Tab::make('bKash')
                                    ->id('bkash')
                                    ->icon('heroicon-o-device-phone-mobile')
                                    ->schema($this->bkashFormSchema()),
                                Tab::make('Nagad')
                                    ->id('nagad')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema($this->nagadFormSchema()),
                                Tab::make('SSLCommerz')
                                    ->id('sslcommerz')
                                    ->icon('heroicon-o-credit-card')
                                    ->schema($this->sslCommerzFormSchema()),
                                Tab::make('PipraPay')
                                    ->id('piprapay')
                                    ->icon('heroicon-o-globe-alt')
                                    ->schema($this->pipraPayFormSchema()),
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
                                            ->columnSpanFull(),
                                        Placeholder::make('rocket_webhook_hint')
                                            ->label('Webhook')
                                            ->content('POST /api/webhooks/payments/rocket with secret ROCKET_WEBHOOK_SECRET'),
                                    ])
                                    ->columns(2),
                            ])
                            ->persistTabInQueryString('gateway'),
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
            Section::make('Nagad checkout')->schema([
                \Filament\Forms\Components\Toggle::make('nagad_enabled')->label('Enable Nagad'),
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
            Section::make('bKash — payment gateway information')
                ->description('bKash Tokenized API (Web/URL) — same flow as merchant checkout with callback URL.')
                ->schema([
                    Select::make('bkash_gateway_type')
                        ->label('Gateway type')
                        ->options([
                            BkashSettings::GATEWAY_TOKENIZED_WEB => 'bKash Tokenized API (Web/URL) Pay',
                        ])
                        ->disabled()
                        ->dehydrated()
                        ->default(BkashSettings::GATEWAY_TOKENIZED_WEB),
                    Radio::make('bkash_environment')
                        ->label('Sandbox / Live')
                        ->options([
                            BkashSettings::ENV_SANDBOX => 'Sandbox (test)',
                            BkashSettings::ENV_LIVE => 'Live (production)',
                        ])
                        ->inline()
                        ->live()
                        ->required(),
                    Placeholder::make('bkash_base_url_preview')
                        ->label('API base URL (auto)')
                        ->content(fn ($get): string => BkashSettings::baseUrlForEnvironment(
                            (string) ($get('bkash_environment') ?? BkashSettings::ENV_SANDBOX)
                        )),
                    TextInput::make('bkash_app_key')
                        ->label('App key (X-APP-Key)')
                        ->maxLength(255)
                        ->required(),
                    TextInput::make('bkash_app_secret')
                        ->label('App secret')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->helperText('Leave blank to keep the saved secret.'),
                    TextInput::make('bkash_username')
                        ->label('Username')
                        ->maxLength(255)
                        ->required(),
                    TextInput::make('bkash_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->dehydrated(false)
                        ->helperText('Leave blank to keep the saved password.'),
                    TextInput::make('bkash_http_timeout')
                        ->label('HTTP timeout (seconds)')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(120)
                        ->required(),
                    TextInput::make('bkash_callback_url')
                        ->label('Callback URL (must match bKash merchant panel)')
                        ->url()
                        ->maxLength(255)
                        ->required()
                        ->helperText(BkashSettings::callbackUrlHint().' Your site: '.rtrim((string) config('app.url'), '/').'/bkash/callback')
                        ->placeholder(fn (): string => rtrim((string) config('app.url'), '/').'/bkash/callback'),
                ])
                ->columns(1),
            Section::make('Payment status')
                ->schema([
                    Radio::make('bkash_enabled')
                        ->label('Payment gateway status')
                        ->options([
                            '1' => 'Payment enabled',
                            '0' => 'Payment disabled',
                        ])
                        ->inline()
                        ->required(),
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
                        ->required(),
                ]),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('testBkash')
                ->label('Test connection')
                ->color('gray')
                ->action('testBkashConnection')
                ->visible(true),
            Action::make('save')
                ->label('Save or update')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function testBkashConnection(): void
    {
        abort_unless(static::canAccess(), 403);

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

        $state = $this->form->getState();
        $before = $this->bkashSnapshot();

        $environment = (string) ($state['bkash_environment'] ?? BkashSettings::ENV_SANDBOX);
        if (! in_array($environment, [BkashSettings::ENV_SANDBOX, BkashSettings::ENV_LIVE], true)) {
            $environment = BkashSettings::ENV_SANDBOX;
        }

        AppSetting::putValue('bkash.gateway_type', BkashSettings::GATEWAY_TOKENIZED_WEB);
        AppSetting::putValue('bkash.environment', $environment);
        AppSetting::putValue('bkash.base_url', BkashSettings::baseUrlForEnvironment($environment));
        AppSetting::putValue('bkash.enabled', ($state['bkash_enabled'] ?? '0') === '1' ? '1' : '0');
        AppSetting::putValue('bkash.app_key', trim((string) ($state['bkash_app_key'] ?? '')));
        AppSetting::putValue('bkash.username', trim((string) ($state['bkash_username'] ?? '')));
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
        AppSetting::putValue('bkash.channels', BkashSettings::channelsToStorage($channels));

        $rawSecret = (string) ($this->data['bkash_app_secret'] ?? '');
        if ($rawSecret !== '') {
            AppSetting::putValue('bkash.app_secret', $rawSecret);
        }

        $rawPass = (string) ($this->data['bkash_password'] ?? '');
        if ($rawPass !== '') {
            AppSetting::putValue('bkash.password', $rawPass);
        }

        AppSetting::putValue('rocket.enabled', ($state['rocket_enabled'] ?? '0') === '1' ? '1' : '0');
        AppSetting::putValue('rocket.merchant_number', trim((string) ($state['rocket_merchant_number'] ?? '')));
        AppSetting::putValue('rocket.merchant_name', trim((string) ($state['rocket_merchant_name'] ?? '')));
        AppSetting::putValue('rocket.instructions', trim((string) ($state['rocket_instructions'] ?? '')));
        AppSetting::putValue('rocket.auto_verify', ($state['rocket_auto_verify'] ?? false) ? '1' : '0');
        AppSetting::putValue('rocket.verify_url', trim((string) ($state['rocket_verify_url'] ?? '')));
        config([
            'payments.gateways.rocket.enabled' => ($state['rocket_enabled'] ?? '0') === '1',
        ]);

        AppSetting::putValue('nagad.enabled', ($state['nagad_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('nagad.sandbox', ($state['nagad_sandbox'] ?? '1') === '1' ? '1' : '0');
        AppSetting::putValue('nagad.merchant_id', trim((string) ($state['nagad_merchant_id'] ?? '')));
        AppSetting::putValue('nagad.merchant_number', trim((string) ($state['nagad_merchant_number'] ?? '')));
        AppSetting::putValue('nagad.pg_public_key', trim((string) ($state['nagad_pg_public_key'] ?? '')));
        $nagadPrivate = trim((string) ($this->data['nagad_merchant_private_key'] ?? ''));
        if ($nagadPrivate !== '') {
            AppSetting::putValue('nagad.merchant_private_key', $nagadPrivate);
        }

        AppSetting::putValue('sslcommerz.enabled', ($state['sslcommerz_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('sslcommerz.sandbox', ($state['sslcommerz_sandbox'] ?? '1') === '1' ? '1' : '0');
        AppSetting::putValue('sslcommerz.store_id', trim((string) ($state['sslcommerz_store_id'] ?? '')));
        $sslPass = trim((string) ($this->data['sslcommerz_store_password'] ?? ''));
        if ($sslPass !== '') {
            AppSetting::putValue('sslcommerz.store_password', $sslPass);
        }

        AppSetting::putValue('piprapay.enabled', ($state['piprapay_enabled'] ?? false) ? '1' : '0');
        $apiMode = (string) ($state['piprapay_api_mode'] ?? 'redirect');
        if (! in_array($apiMode, ['redirect', 'legacy'], true)) {
            $apiMode = 'redirect';
        }
        AppSetting::putValue('piprapay.api_mode', $apiMode);
        $pipraKey = trim((string) ($this->data['piprapay_api_key'] ?? ''));
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

        AppSetting::syncToRuntimeConfig();

        $this->data['bkash_app_secret'] = '';
        $this->data['bkash_password'] = '';

        $after = $this->bkashSnapshot();
        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Payment gateway (bKash) updated',
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

        Notification::make()
            ->title('Payment settings saved')
            ->success()
            ->send();
    }

    private function fillBkashForm(): void
    {
        $environment = (string) config('bkash.environment', BkashSettings::ENV_SANDBOX);
        if (! in_array($environment, [BkashSettings::ENV_SANDBOX, BkashSettings::ENV_LIVE], true)) {
            $environment = BkashSettings::detectEnvironmentFromBaseUrl((string) config('bkash.base_url'));
        }

        $this->form->fill([
            'bkash_gateway_type' => (string) config('bkash.gateway_type', BkashSettings::GATEWAY_TOKENIZED_WEB),
            'bkash_environment' => $environment,
            'bkash_app_key' => (string) (config('bkash.app_key') ?? ''),
            'bkash_app_secret' => '',
            'bkash_username' => (string) (config('bkash.username') ?? ''),
            'bkash_password' => '',
            'bkash_http_timeout' => (int) config('bkash.http_timeout', 30),
            'bkash_callback_url' => BkashSettings::callbackUrl(),
            'bkash_enabled' => config('bkash.enabled') ? '1' : '0',
            'bkash_activation_date' => config('bkash.activation_date'),
            'bkash_expiry_date' => config('bkash.expiry_date'),
            'bkash_channels' => BkashSettings::enabledChannels(),
            'rocket_enabled' => config('rocket.enabled') ? '1' : '0',
            'rocket_merchant_number' => (string) config('rocket.merchant_number', ''),
            'rocket_merchant_name' => (string) config('rocket.merchant_name', ''),
            'rocket_instructions' => (string) config('rocket.instructions', ''),
            'rocket_auto_verify' => config('rocket.auto_verify') ? '1' : '0',
            'rocket_verify_url' => (string) config('rocket.verify_url', ''),
            'nagad_enabled' => config('nagad.enabled') ? '1' : '0',
            'nagad_sandbox' => config('nagad.sandbox') ? '1' : '0',
            'nagad_merchant_id' => (string) config('nagad.merchant_id', ''),
            'nagad_merchant_number' => (string) config('nagad.merchant_number', ''),
            'nagad_pg_public_key' => (string) config('nagad.pg_public_key', ''),
            'sslcommerz_enabled' => config('sslcommerz.enabled') ? '1' : '0',
            'sslcommerz_sandbox' => config('sslcommerz.sandbox') ? '1' : '0',
            'sslcommerz_store_id' => (string) config('sslcommerz.store_id', ''),
            'piprapay_enabled' => config('piprapay.enabled') ? '1' : '0',
            'piprapay_api_mode' => (string) config('piprapay.api_mode', 'redirect'),
            'piprapay_base_url' => (string) config('piprapay.base_url', ''),
            'piprapay_public_url' => (string) config('piprapay.public_url', config('app.url')),
        ]);
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
