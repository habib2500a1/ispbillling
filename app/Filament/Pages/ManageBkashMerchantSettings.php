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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * bKash Tokenized Merchant API only — no PipraPay / Nagad tabs.
 *
 * @property Form $form
 */
class ManageBkashMerchantSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static string $view = 'filament.pages.manage-bkash-merchant-settings';

    protected static ?string $slug = 'bkash-merchant-settings';

    protected static ?string $navigationLabel = 'bKash Merchant API';

    protected static ?string $title = 'bKash Merchant API';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

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

        $this->fillForm();
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
                    ->schema($this->formSchema())
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function formSchema(): array
    {
        return [
            Placeholder::make('bkash_personal_link')
                ->label('bKash Personal (Send Money + TrxID)?')
                ->content(new HtmlString(
                    '<a class="text-primary-600 font-semibold underline" href="'
                    .ManagePersonalMfsSettings::getUrl(['tab' => 'bkash'])
                    .'">Open bKash Personal settings</a> — separate from this merchant API page.'
                )),
            Placeholder::make('bkash_mode_status')
                ->label('Status on /pay & customer portal')
                ->content(function (): HtmlString {
                    $s = BkashSettings::statusSummary();
                    $lines = [];
                    $lines[] = $s['personal']
                        ? '<span class="text-success-600">Personal: ON</span> ('.e($s['personal_number']).')'
                        : '<span class="text-gray-500">Personal: OFF</span>';
                    if ($s['merchant'] && $s['merchant_configured']) {
                        $lines[] = '<span class="text-success-600">Merchant API: ON & ready</span>';
                    } elseif ($s['merchant']) {
                        $lines[] = '<span class="text-warning-600">Merchant API: ON — add missing credentials below</span>';
                    } else {
                        $lines[] = '<span class="text-gray-500">Merchant API: OFF</span>';
                    }

                    return new HtmlString('<div class="text-sm space-y-1">'.implode('<br>', $lines).'</div>');
                })
                ->columnSpanFull(),
            Section::make('bKash credentials (Tokenized API v1.2)')
                ->description('From bKash merchant developer panel. Callback URL must match exactly.')
                ->schema([
                    Radio::make('bkash_environment')
                        ->label('Environment')
                        ->options([
                            BkashSettings::ENV_SANDBOX => 'Sandbox (test)',
                            BkashSettings::ENV_LIVE => 'Live (production)',
                        ])
                        ->inline()
                        ->live()
                        ->default(BkashSettings::ENV_SANDBOX),
                    Placeholder::make('bkash_base_url_preview')
                        ->label('API base URL (auto)')
                        ->content(fn ($get): string => BkashSettings::baseUrlForEnvironment(
                            (string) ($get('bkash_environment') ?? BkashSettings::ENV_SANDBOX)
                        )),
                    TextInput::make('bkash_app_key')
                        ->label('App key')
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
                        ->label('Callback URL')
                        ->maxLength(255)
                        ->placeholder(fn (): string => BkashSettings::defaultCallbackUrl())
                        ->helperText(BkashSettings::callbackUrlHint()),
                ])
                ->columns(1),
            Section::make('Enable merchant checkout')
                ->schema([
                    Radio::make('bkash_enabled')
                        ->label('bKash Merchant API on /pay & portal')
                        ->options([
                            '1' => 'Enabled',
                            '0' => 'Disabled',
                        ])
                        ->inline()
                        ->default('0'),
                ]),
            Section::make('Schedule (optional)')
                ->schema([
                    DatePicker::make('bkash_activation_date')->label('Activation date')->native(false),
                    DatePicker::make('bkash_expiry_date')
                        ->label('Expiry date')
                        ->native(false)
                        ->after('bkash_activation_date'),
                ])
                ->columns(2),
            Section::make('Show on')
                ->schema([
                    CheckboxList::make('bkash_channels')
                        ->label('Channels')
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
        return [
            Action::make('testBkash')
                ->label('Test connection')
                ->color('gray')
                ->action('testBkashConnection'),
            Action::make('save')
                ->label('Save bKash Merchant API')
                ->color('primary')
                ->action('save'),
        ];
    }

    public function testBkashConnection(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->applyFormToRuntimeConfig($this->data ?? []);

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

        $state = $this->data ?? [];
        $merchantOn = $this->toBool($state['bkash_enabled'] ?? '0');

        if ($merchantOn && ! $this->credentialsComplete($state)) {
            $missing = $this->missingCredentialLabels($state);
            Notification::make()
                ->title('Cannot save — missing fields')
                ->body('Merchant is Enabled. Required: '.implode(', ', $missing).'.')
                ->danger()
                ->send();

            return;
        }

        $environment = (string) ($state['bkash_environment'] ?? BkashSettings::ENV_SANDBOX);
        if (! in_array($environment, [BkashSettings::ENV_SANDBOX, BkashSettings::ENV_LIVE], true)) {
            $environment = BkashSettings::ENV_SANDBOX;
        }

        $personalOn = BkashSettings::isPersonalEnabled();

        AppSetting::putValues([
            'bkash.merchant_enabled' => $merchantOn ? '1' : '0',
            'bkash.environment' => $environment,
            'bkash.base_url' => BkashSettings::baseUrlForEnvironment($environment),
            'bkash.http_timeout' => (string) max(5, min(120, (int) ($state['bkash_http_timeout'] ?? 30))),
            'bkash.enabled' => ($personalOn || $merchantOn) ? '1' : '0',
        ]);

        $appKey = trim((string) ($state['bkash_app_key'] ?? ''));
        if ($appKey !== '') {
            AppSetting::putValue('bkash.app_key', $appKey);
        }

        $username = trim((string) ($state['bkash_username'] ?? ''));
        if ($username !== '') {
            AppSetting::putValue('bkash.username', $username);
        }

        $secret = trim((string) ($state['bkash_app_secret'] ?? ''));
        if ($secret !== '') {
            AppSetting::putValue('bkash.app_secret', $secret);
        }

        $password = trim((string) ($state['bkash_password'] ?? ''));
        if ($password !== '') {
            AppSetting::putValue('bkash.password', $password);
        }

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
            $channels = BkashSettings::allChannels();
        }
        AppSetting::putValue('bkash.channels', BkashSettings::channelsToStorage($channels));

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'bKash Merchant API settings saved',
                'context' => [
                    'merchant_enabled' => $merchantOn,
                    'environment' => $environment,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('bkash_merchant_settings.audit_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        $this->fillForm();
        $this->data['bkash_app_secret'] = '';
        $this->data['bkash_password'] = '';

        $summary = BkashSettings::statusSummary();

        Notification::make()
            ->title('bKash Merchant API saved')
            ->body(
                'Merchant: '.($summary['merchant']
                    ? ($summary['merchant_configured'] ? 'ON — ready for checkout' : 'ON — credentials still incomplete')
                    : 'OFF')
                .' · Callback: '.BkashSettings::callbackUrl()
            )
            ->success()
            ->send();
    }

    private function fillForm(): void
    {
        AppSetting::syncToRuntimeConfig();

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
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function applyFormToRuntimeConfig(array $state): void
    {
        if ($key = trim((string) ($state['bkash_app_key'] ?? ''))) {
            config(['bkash.app_key' => $key]);
        }
        if ($secret = trim((string) ($state['bkash_app_secret'] ?? ''))) {
            config(['bkash.app_secret' => $secret]);
        }
        if ($user = trim((string) ($state['bkash_username'] ?? ''))) {
            config(['bkash.username' => $user]);
        }
        if ($pass = trim((string) ($state['bkash_password'] ?? ''))) {
            config(['bkash.password' => $pass]);
        }

        $environment = (string) ($state['bkash_environment'] ?? BkashSettings::ENV_SANDBOX);
        config([
            'bkash.environment' => $environment,
            'bkash.base_url' => BkashSettings::baseUrlForEnvironment($environment),
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function credentialsComplete(array $state): bool
    {
        return $this->missingCredentialLabels($state) === [];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function missingCredentialLabels(array $state): array
    {
        $missing = [];

        if (trim((string) ($state['bkash_app_key'] ?? '')) === '' && ! filled(config('bkash.app_key'))) {
            $missing[] = 'App key';
        }
        if (trim((string) ($state['bkash_app_secret'] ?? '')) === '' && ! filled(AppSetting::getStoredValue('bkash.app_secret'))) {
            $missing[] = 'App secret';
        }
        if (trim((string) ($state['bkash_username'] ?? '')) === '' && ! filled(config('bkash.username'))) {
            $missing[] = 'Username';
        }
        if (trim((string) ($state['bkash_password'] ?? '')) === '' && ! filled(AppSetting::getStoredValue('bkash.password'))) {
            $missing[] = 'Password';
        }

        return $missing;
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
}
