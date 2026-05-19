<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Services\Notifications\Channels\SmsNotificationChannel;
use App\Services\Notifications\SmsBalanceFetcher;
use App\Services\Notifications\SmsGatewayStatsService;
use App\Services\Tenant\TenantScopedConfig;
use App\Support\KhudeBartaUrls;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @property Form $form
 */
class SmsGatewaySetup extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static string $view = 'filament.pages.sms-gateway-setup';

    protected static ?string $navigationLabel = 'SMS Gateway';

    protected static ?string $title = 'SMS Gateway Setup';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'sms-gateway';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public bool $refreshBalanceOnLoad = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'sms_enabled' => (bool) config('notifications.sms.enabled', false),
            'sms_provider' => (string) config('notifications.sms.provider', 'khudebarta'),
            'sms_api_url' => (string) config('notifications.sms.api_url', ''),
            'sms_api_key' => (string) config('notifications.sms.api_key', ''),
            'sms_sender_id' => (string) config('notifications.sms.sender_id', 'ISP'),
            'sms_secret_key' => '',
            'manual_balance' => AppSetting::getStoredValue('notifications.sms.cached_balance'),
            'khudebarta_dlr_url' => KhudeBartaUrls::dlrCallbackUrl(),
        ]);
        $this->refreshBalanceOnLoad = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSmsStats(): array
    {
        return app(SmsGatewayStatsService::class)->snapshot($this->refreshBalanceOnLoad);
    }

    public function refreshBalance(): void
    {
        Cache::forget('sms_gateway_balance_snapshot');
        app(SmsBalanceFetcher::class)->fetch(true);
        $this->refreshBalanceOnLoad = true;
        Notification::make()->title('Balance refreshed')->success()->send();
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
                        Section::make('SMS Settings')
                            ->description('Gateway credentials used for bill reminders, OTP, payment alerts, and bulk SMS.')
                            ->schema([
                                Toggle::make('sms_enabled')
                                    ->label('Enable SMS')
                                    ->columnSpanFull(),
                                Select::make('sms_provider')
                                    ->label('SMS Provider')
                                    ->options([
                                        'khudebarta' => 'KhudeBarta (v2.0)',
                                        'bulksmsbd' => 'BulkSMSBD',
                                        'sslwireless' => 'SSL Wireless',
                                        'custom' => 'Custom HTTP',
                                    ])
                                    ->native(false)
                                    ->live()
                                    ->required(),
                                TextInput::make('sms_api_key')
                                    ->label('SMS User Name / API Key')
                                    ->helperText('KhudeBarta API key from your portal.')
                                    ->maxLength(255)
                                    ->dehydrated(false),
                                TextInput::make('sms_secret_key')
                                    ->label('SMS Password / Secret Key')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->visible(fn ($get): bool => in_array($get('sms_provider'), ['khudebarta'], true)),
                                TextInput::make('sms_sender_id')
                                    ->label('SMS Sender / Caller ID')
                                    ->helperText('Approved masking name (Caller ID).')
                                    ->maxLength(32),
                                TextInput::make('sms_api_url')
                                    ->label('API URL')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder(fn ($get): string => $get('sms_provider') === 'khudebarta'
                                        ? 'http://portal.khudebarta.com:3775/sendtext'
                                        : '')
                                    ->columnSpanFull(),
                                Placeholder::make('khudebarta_dlr_url')
                                    ->label('DLR callback URL')
                                    ->content(fn ($get): string => (string) ($get('khudebarta_dlr_url') ?: KhudeBartaUrls::dlrCallbackUrl()))
                                    ->visible(fn ($get): bool => $get('sms_provider') === 'khudebarta')
                                    ->columnSpanFull(),
                                TextInput::make('manual_balance')
                                    ->label('SMS balance (manual)')
                                    ->numeric()
                                    ->step(0.01)
                                    ->helperText('If live balance API is unavailable, enter remaining SMS credit from the KhudeBarta portal. Click Refresh balance after saving.')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshBalance')
                ->label('Refresh balance')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshBalance()),
            Action::make('testSms')
                ->label('Send test SMS')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->form([
                    TextInput::make('sms_test_phone')
                        ->label('Phone (01XXXXXXXXX)')
                        ->required()
                        ->tel(),
                    TextInput::make('sms_test_message')
                        ->label('Message')
                        ->default('Flixbd test SMS — '.now()->format('Y-m-d H:i'))
                        ->maxLength(160),
                ])
                ->action(function (array $data): void {
                    if (! config('notifications.sms.enabled', false)) {
                        Notification::make()->title('SMS disabled')->danger()->send();

                        return;
                    }
                    try {
                        app(SmsNotificationChannel::class)->send(
                            (string) $data['sms_test_phone'],
                            (string) ($data['sms_test_message'] ?? 'Test'),
                        );
                        Notification::make()->title('Test SMS sent')->success()->send();
                        $this->refreshBalanceOnLoad = true;
                    } catch (\Throwable $e) {
                        Notification::make()->title('SMS failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Update SMS gateway')
                ->icon('heroicon-o-check')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        AppSetting::putValue('notifications.sms.enabled', $this->truthy($state['sms_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('notifications.sms.provider', (string) ($state['sms_provider'] ?? 'khudebarta'));
        AppSetting::putValue('notifications.sms.api_url', rtrim((string) ($state['sms_api_url'] ?? ''), '/'));
        AppSetting::putValue('notifications.sms.sender_id', (string) ($state['sms_sender_id'] ?? 'ISP'));

        $smsKey = trim((string) ($this->data['sms_api_key'] ?? ''));
        if ($smsKey !== '') {
            AppSetting::putValue('notifications.sms.api_key', $smsKey);
        }

        $smsSecret = trim((string) ($this->data['sms_secret_key'] ?? ''));
        if ($smsSecret !== '') {
            AppSetting::putValue('notifications.sms.secret_key', $smsSecret);
        }

        $tenantId = TenantResolver::currentTenantId() ?? auth()->user()?->tenant_id;
        if ($tenantId) {
            TenantScopedConfig::put((int) $tenantId, 'notifications.sms.enabled', $this->truthy($state['sms_enabled'] ?? false) ? '1' : '0');
            TenantScopedConfig::put((int) $tenantId, 'notifications.sms.provider', (string) ($state['sms_provider'] ?? 'khudebarta'));
            TenantScopedConfig::put((int) $tenantId, 'notifications.sms.api_url', rtrim((string) ($state['sms_api_url'] ?? ''), '/'));
            TenantScopedConfig::put((int) $tenantId, 'notifications.sms.sender_id', (string) ($state['sms_sender_id'] ?? 'ISP'));
            if ($smsKey !== '') {
                TenantScopedConfig::put((int) $tenantId, 'notifications.sms.api_key', $smsKey);
            }
            if ($smsSecret !== '') {
                TenantScopedConfig::put((int) $tenantId, 'notifications.sms.secret_key', $smsSecret);
            }
            TenantScopedConfig::apply((int) $tenantId);
        }

        $manualBalance = trim((string) ($state['manual_balance'] ?? ''));
        if ($manualBalance !== '' && is_numeric($manualBalance)) {
            AppSetting::putValue('notifications.sms.cached_balance', $manualBalance);
            AppSetting::putValue('notifications.sms.cached_balance_at', now()->toIso8601String());
        }

        AppSetting::syncToRuntimeConfig();
        Cache::forget('sms_gateway_balance_snapshot');

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'SMS gateway settings updated',
                'context' => ['tenant_id' => TenantResolver::currentTenantId()],
            ]);
        } catch (\Throwable $e) {
            Log::warning('sms_gateway.audit_failed', ['error' => $e->getMessage()]);
        }

        Notification::make()->title('SMS gateway saved')->success()->send();
        $this->refreshBalanceOnLoad = true;
    }

    private function truthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
