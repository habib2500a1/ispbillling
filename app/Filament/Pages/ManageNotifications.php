<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Services\Notifications\Channels\SmsNotificationChannel;
use App\Services\Tenant\TenantScopedConfig;
use App\Support\KhudeBartaUrls;
use App\Support\TenantResolver;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use Filament\Forms\Components\Placeholder;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

/**
 * @property Form $form
 */
class ManageNotifications extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static string $view = 'filament.pages.manage-notifications';

    protected static ?string $navigationLabel = 'Notification settings';

    protected static ?string $title = 'Notification channels & templates';

    protected static ?string $navigationGroup = 'SMS Service';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

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
            'log_delivery_only' => (bool) config('notifications.log_delivery_only', false),
            'email_enabled' => (bool) config('notifications.email.enabled', true),
            'sms_enabled' => (bool) config('notifications.sms.enabled', false),
            'sms_provider' => (string) config('notifications.sms.provider', 'bulksmsbd'),
            'sms_api_url' => (string) config('notifications.sms.api_url', ''),
            'sms_api_key' => '',
            'sms_sender_id' => (string) config('notifications.sms.sender_id', 'ISP'),
            'khudebarta_dlr_url' => KhudeBartaUrls::dlrCallbackUrl(),
            'sms_test_phone' => '',
            'whatsapp_enabled' => (bool) config('notifications.whatsapp.enabled', false),
            'whatsapp_phone_number_id' => (string) (config('notifications.whatsapp.phone_number_id') ?? ''),
            'whatsapp_access_token' => '',
            'telegram_enabled' => (bool) config('notifications.telegram.enabled', false),
            'telegram_bot_token' => '',
            'telegram_ops_chat_id' => (string) (config('notifications.telegram.ops_chat_id') ?? ''),
            'event_payment_enabled' => (bool) config('notifications.events.payment_success.enabled', true),
            'event_payment_channels' => config('notifications.events.payment_success.channels', ['email', 'sms']),
            'event_payment_telegram_ops' => (bool) config('notifications.events.payment_success.telegram_ops', true),
            'event_due_enabled' => (bool) config('notifications.events.invoice_due.enabled', false),
            'event_due_days' => (int) config('notifications.events.invoice_due.days_before', 3),
            'event_due_channels' => config('notifications.events.invoice_due.channels', ['email', 'sms']),
            'event_outage_channels' => config('notifications.events.outage.channels', ['email', 'sms', 'whatsapp']),
            'event_outage_telegram_ops' => (bool) config('notifications.events.outage.telegram_ops', true),
            'event_otp_channels' => config('notifications.events.portal_otp.channels', ['email', 'sms']),
            'tpl_payment_success' => (string) config('notifications.templates.payment_success'),
            'tpl_invoice_due' => (string) config('notifications.templates.invoice_due'),
            'tpl_outage' => (string) config('notifications.templates.outage'),
            'tpl_portal_otp' => (string) config('notifications.templates.portal_otp'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        $channelOptions = NotificationChannel::labels();

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Delivery mode')
                            ->description('Log-only writes to delivery history without calling external APIs — useful for testing.')
                            ->schema([
                                Toggle::make('log_delivery_only')
                                    ->label('Log delivery only (no external send)'),
                            ]),
                        Section::make('Email')
                            ->schema([
                                Toggle::make('email_enabled')->label('Enable email channel'),
                            ]),
                        Section::make('SMS gateway')
                            ->description('KhudeBarta (Softifybd), BulkSMSBD, SSL Wireless, or custom HTTP.')
                            ->schema([
                                Toggle::make('sms_enabled')->label('Enable SMS'),
                                Select::make('sms_provider')
                                    ->label('Provider')
                                    ->options([
                                        'khudebarta' => 'KhudeBarta (Softifybd HTTP JSON)',
                                        'bulksmsbd' => 'BulkSMSBD',
                                        'sslwireless' => 'SSL Wireless',
                                        'custom' => 'Custom HTTP',
                                    ])
                                    ->native(false)
                                    ->live()
                                    ->required(),
                                TextInput::make('sms_api_url')
                                    ->label('API URL')
                                    ->url()
                                    ->maxLength(500)
                                    ->placeholder(fn ($get): string => $get('sms_provider') === 'khudebarta'
                                        ? 'http://portal.khudebarta.com:3775/sendtext'
                                        : ''),
                                TextInput::make('sms_api_key')
                                    ->label('API key')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false),
                                TextInput::make('sms_secret_key')
                                    ->label('Secret key')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->visible(fn ($get): bool => $get('sms_provider') === 'khudebarta'),
                                TextInput::make('sms_sender_id')
                                    ->label('Caller ID / Sender ID')
                                    ->helperText('KhudeBarta: approved masking name (callerID).')
                                    ->maxLength(32),
                                Placeholder::make('khudebarta_dlr_url')
                                    ->label('KhudeBarta DLR callback URL')
                                    ->content(fn ($get): string => (string) ($get('khudebarta_dlr_url') ?: KhudeBartaUrls::dlrCallbackUrl()))
                                    ->helperText('Paste in KhudeBarta portal → Delivery API (Query). Optional override: KHUDEBARTA_DLR_URL in .env')
                                    ->visible(fn ($get): bool => $get('sms_provider') === 'khudebarta')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                        Section::make('WhatsApp (Meta Cloud API)')
                            ->schema([
                                Toggle::make('whatsapp_enabled')->label('Enable WhatsApp'),
                                TextInput::make('whatsapp_phone_number_id')->label('Phone number ID')->maxLength(64),
                                TextInput::make('whatsapp_access_token')
                                    ->label('Access token')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false),
                            ])
                            ->columns(2),
                        Section::make('Telegram (operations alerts)')
                            ->schema([
                                Toggle::make('telegram_enabled')->label('Enable Telegram'),
                                TextInput::make('telegram_bot_token')
                                    ->label('Bot token')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false),
                                TextInput::make('telegram_ops_chat_id')
                                    ->label('Ops chat ID')
                                    ->helperText('Staff channel for payment/outage digests'),
                            ])
                            ->columns(2),
                        Section::make('Automated events')
                            ->schema([
                                Toggle::make('event_payment_enabled')->label('Payment success alerts'),
                                CheckboxList::make('event_payment_channels')
                                    ->label('Payment channels')
                                    ->options($channelOptions)
                                    ->columns(2),
                                Toggle::make('event_payment_telegram_ops')->label('Telegram ops on payment'),
                                Toggle::make('event_due_enabled')->label('Invoice due reminders'),
                                TextInput::make('event_due_days')
                                    ->label('Days before due date')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(30),
                                CheckboxList::make('event_due_channels')
                                    ->label('Due reminder channels')
                                    ->options($channelOptions)
                                    ->columns(2),
                                CheckboxList::make('event_outage_channels')
                                    ->label('Outage broadcast channels')
                                    ->options($channelOptions)
                                    ->columns(2),
                                Toggle::make('event_outage_telegram_ops')->label('Telegram ops on outage'),
                                CheckboxList::make('event_otp_channels')
                                    ->label('Portal OTP channels')
                                    ->options($channelOptions)
                                    ->columns(2),
                            ])
                            ->columns(1),
                        Section::make('Message templates')
                            ->description('Placeholders: {name} {amount} {invoice_number} {receipt_number} {balance} {due_date} {code} {minutes} {message}')
                            ->schema([
                                Textarea::make('tpl_payment_success')->label('Payment success')->rows(3),
                                Textarea::make('tpl_invoice_due')->label('Invoice due')->rows(3),
                                Textarea::make('tpl_outage')->label('Outage')->rows(3),
                                Textarea::make('tpl_portal_otp')->label('Portal OTP')->rows(2),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('testSms')
                ->label('Send test SMS')
                ->color('gray')
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    TextInput::make('sms_test_phone')
                        ->label('Phone (01XXXXXXXXX)')
                        ->required()
                        ->tel(),
                    TextInput::make('sms_test_message')
                        ->label('Message')
                        ->default('ISP Platform test SMS — '.now()->format('Y-m-d H:i'))
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
                    } catch (\Throwable $e) {
                        Notification::make()->title('SMS failed')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        AppSetting::putValue('notifications.log_delivery_only', $this->truthy($state['log_delivery_only'] ?? false) ? '1' : '0');
        AppSetting::putValue('notifications.email.enabled', $this->truthy($state['email_enabled'] ?? true) ? '1' : '0');
        AppSetting::putValue('notifications.sms.enabled', $this->truthy($state['sms_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('notifications.sms.provider', (string) ($state['sms_provider'] ?? 'bulksmsbd'));
        AppSetting::putValue('notifications.sms.api_url', rtrim((string) ($state['sms_api_url'] ?? ''), '/'));
        AppSetting::putValue('notifications.sms.sender_id', (string) ($state['sms_sender_id'] ?? 'ISP'));

        $dlrUrl = trim((string) ($state['khudebarta_dlr_url'] ?? ''));
        if ($dlrUrl !== '' && ($state['sms_provider'] ?? '') === 'khudebarta') {
            AppSetting::putValue('notifications.sms.khudebarta_dlr_url', $dlrUrl);
        }

        $smsKey = trim((string) ($this->data['sms_api_key'] ?? ''));
        if ($smsKey !== '') {
            AppSetting::putValue('notifications.sms.api_key', $smsKey);
        }

        $smsSecret = trim((string) ($this->data['sms_secret_key'] ?? ''));
        if ($smsSecret !== '') {
            AppSetting::putValue('notifications.sms.secret_key', $smsSecret);
        }

        AppSetting::putValue('notifications.whatsapp.enabled', $this->truthy($state['whatsapp_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('notifications.whatsapp.phone_number_id', trim((string) ($state['whatsapp_phone_number_id'] ?? '')));

        $waToken = trim((string) ($this->data['whatsapp_access_token'] ?? ''));
        if ($waToken !== '') {
            AppSetting::putValue('notifications.whatsapp.access_token', $waToken);
        }

        AppSetting::putValue('notifications.telegram.enabled', $this->truthy($state['telegram_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('notifications.telegram.ops_chat_id', trim((string) ($state['telegram_ops_chat_id'] ?? '')));

        $tgToken = trim((string) ($this->data['telegram_bot_token'] ?? ''));
        if ($tgToken !== '') {
            AppSetting::putValue('notifications.telegram.bot_token', $tgToken);
        }

        AppSetting::putValue('notifications.events.payment_success.enabled', $this->truthy($state['event_payment_enabled'] ?? true) ? '1' : '0');
        AppSetting::putValue('notifications.events.payment_success.channels', $this->channelsCsv($state['event_payment_channels'] ?? []));
        AppSetting::putValue('notifications.events.payment_success.telegram_ops', $this->truthy($state['event_payment_telegram_ops'] ?? false) ? '1' : '0');

        $dueEnabled = $this->truthy($state['event_due_enabled'] ?? false);
        AppSetting::putValue('notifications.events.invoice_due.enabled', $dueEnabled ? '1' : '0');
        AppSetting::putValue('notifications.events.invoice_due.days_before', (string) max(1, min(30, (int) ($state['event_due_days'] ?? 3))));
        AppSetting::putValue('notifications.events.invoice_due.channels', $this->channelsCsv($state['event_due_channels'] ?? []));
        AppSetting::putValue('sms.reminders_enabled', $dueEnabled ? '1' : '0');
        AppSetting::putValue('sms.reminders_days_before', (string) max(1, min(30, (int) ($state['event_due_days'] ?? 3))));

        AppSetting::putValue('notifications.events.outage.channels', $this->channelsCsv($state['event_outage_channels'] ?? []));
        AppSetting::putValue('notifications.events.outage.telegram_ops', $this->truthy($state['event_outage_telegram_ops'] ?? true) ? '1' : '0');
        AppSetting::putValue('notifications.events.portal_otp.channels', $this->channelsCsv($state['event_otp_channels'] ?? []));

        AppSetting::putValue('notifications.templates.payment_success', (string) ($state['tpl_payment_success'] ?? ''));
        AppSetting::putValue('notifications.templates.invoice_due', (string) ($state['tpl_invoice_due'] ?? ''));
        AppSetting::putValue('notifications.templates.outage', (string) ($state['tpl_outage'] ?? ''));
        AppSetting::putValue('notifications.templates.portal_otp', (string) ($state['tpl_portal_otp'] ?? ''));

        $tenantId = TenantResolver::currentTenantId() ?? auth()->user()?->tenant_id;
        if ($tenantId) {
            TenantScopedConfig::put((int) $tenantId, 'notifications.sms.enabled', $this->truthy($state['sms_enabled'] ?? false) ? '1' : '0');
            TenantScopedConfig::put((int) $tenantId, 'notifications.sms.provider', (string) ($state['sms_provider'] ?? 'bulksmsbd'));
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

        AppSetting::syncToRuntimeConfig();

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Notification settings updated',
                'context' => ['tenant_id' => TenantResolver::currentTenantId()],
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification_settings.audit_failed', ['error' => $e->getMessage()]);
        }

        Notification::make()->title('Notification settings saved')->success()->send();
    }

    /**
     * @param  list<string>|mixed  $channels
     */
    private function channelsCsv(mixed $channels): string
    {
        if (! is_array($channels)) {
            return 'email';
        }

        return implode(',', array_values(array_filter($channels, 'is_string')));
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
