<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
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
use Illuminate\Support\Facades\Log;

/**
 * @property Form $form
 */
class ManageAppSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string $view = 'filament.pages.manage-app-settings';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?string $title = 'Integrations & environment';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed> | null
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

        $this->form->fill([
            'isp_tenant_base_domain' => (string) config('isp.tenant_base_domain', ''),
            'sms_reminders_enabled' => (bool) config('sms.reminders_enabled'),
            'sms_reminders_days_before' => (int) config('sms.reminders_days_before', 3),
            'network_provisioner_driver' => (string) config('network.provisioner_driver', 'null'),
            'network_mikrotik_push_enabled' => (bool) config('network.mikrotik_push_enabled', true),
            'network_mikrotik_always_push_ppp_on_customer_save' => (bool) config('network.mikrotik_always_push_ppp_on_customer_save', true),
            'network_radius_push_enabled' => (bool) config('network.radius_push_enabled', true),
            'network_auto_suspend_enabled' => (bool) config('network.auto_suspend_enabled', false),
            'network_service_expiry_enforced' => (bool) config('network.service_expiry_enforced', true),
        ]);
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
                        Section::make('Payment gateways')
                            ->schema([
                                Placeholder::make('payment_gateways_link')
                                    ->label('bKash, Nagad, SSLCommerz')
                                    ->content('Configure under Payments → Payment gateways (sandbox/live, credentials, channels).'),
                            ]),
                        Section::make('Multi-tenant (subdomain hint)')
                            ->description('Same as ISP_TENANT_BASE_DOMAIN. Apex host on this domain leaves tenant resolution to logged-in user only.')
                            ->schema([
                                TextInput::make('isp_tenant_base_domain')
                                    ->label('Tenant base domain')
                                    ->placeholder('e.g. isp.example.com')
                                    ->maxLength(255),
                            ]),
                        Section::make('Network suspend / sync (MikroTik + RADIUS)')
                            ->description('Overrides .env for this server. When driver is mikrotik, radius, or both, the toggles gate which backends receive suspend/unsuspend/sync (stubs log to Laravel until you wire real RouterOS/RADIUS in App\\Services\\Network).')
                            ->schema([
                                Select::make('network_provisioner_driver')
                                    ->label('Provisioner driver (runtime)')
                                    ->options([
                                        'null' => 'null (no network hooks)',
                                        'log' => 'log (log only)',
                                        'mikrotik' => 'mikrotik (API path)',
                                        'radius' => 'radius (RADIUS path)',
                                        'both' => 'both (API + RADIUS)',
                                    ])
                                    ->native(false)
                                    ->required(),
                                Toggle::make('network_mikrotik_push_enabled')
                                    ->label('MikroTik API push enabled'),
                                Toggle::make('network_mikrotik_always_push_ppp_on_customer_save')
                                    ->label('Always push PPP to MikroTik on customer save')
                                    ->helperText('When on, saving a customer still calls RouterOS /ppp/secret even if driver is null or radius-only (tenant must have an enabled MikroTik server in the panel).'),
                                Toggle::make('network_radius_push_enabled')
                                    ->label('RADIUS push enabled'),
                                Toggle::make('network_auto_suspend_enabled')
                                    ->label('Auto suspend line on overdue invoice')
                                    ->helperText('When on, overdue bills turn PPP off (except Free/VIP). Runs hourly via isp:network-evaluate-access.'),
                                Toggle::make('network_service_expiry_enforced')
                                    ->label('Auto off when service date expires')
                                    ->helperText('Past “valid until” → Expired status + line off (except Free/VIP).'),
                            ])
                            ->columns(1),
                        Section::make('Invoice reminders')
                            ->description('Due-date reminders moved to System → Notifications (SMS, email, WhatsApp). Legacy keys below still sync when saved from the notifications page.')
                            ->schema([
                                Toggle::make('sms_reminders_enabled')
                                    ->label('Enable due-date reminders (legacy)')
                                    ->helperText('Prefer Notifications hub for channel setup.'),
                                TextInput::make('sms_reminders_days_before')
                                    ->label('Remind up to N days before due date')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(30)
                                    ->required(),
                            ]),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        $before = $this->integrationSnapshot();

        $domain = trim((string) ($state['isp_tenant_base_domain'] ?? ''));
        if ($domain === '') {
            AppSetting::query()->where('key', 'isp.tenant_base_domain')->delete();
            AppSetting::restoreConfigKeyFromEnv('isp.tenant_base_domain');
        } else {
            AppSetting::putValue('isp.tenant_base_domain', strtolower($domain));
        }

        AppSetting::putValue('sms.reminders_enabled', $this->formStateTruthy($state['sms_reminders_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue(
            'sms.reminders_days_before',
            (string) max(1, min(30, (int) ($state['sms_reminders_days_before'] ?? 3)))
        );

        $driver = (string) ($state['network_provisioner_driver'] ?? 'null');
        if (! in_array($driver, ['null', 'log', 'mikrotik', 'radius', 'both'], true)) {
            $driver = 'null';
        }
        AppSetting::putValue('network.provisioner_driver', $driver);
        AppSetting::putValue('network.mikrotik_push_enabled', $this->formStateTruthy($state['network_mikrotik_push_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue(
            'network.mikrotik_always_push_ppp_on_customer_save',
            $this->formStateTruthy($state['network_mikrotik_always_push_ppp_on_customer_save'] ?? false) ? '1' : '0'
        );
        AppSetting::putValue('network.radius_push_enabled', $this->formStateTruthy($state['network_radius_push_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('network.auto_suspend_enabled', $this->formStateTruthy($state['network_auto_suspend_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('network.service_expiry_enforced', $this->formStateTruthy($state['network_service_expiry_enforced'] ?? false) ? '1' : '0');

        AppSetting::syncToRuntimeConfig();

        $after = $this->integrationSnapshot();
        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Integrations & reminders updated',
                'context' => [
                    'diff' => $this->diffIntegrationSnapshot($before, $after),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('integrations.audit_log_failed', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function integrationSnapshot(): array
    {
        return [
            'isp.tenant_base_domain' => (string) config('isp.tenant_base_domain', ''),
            'sms.reminders_enabled' => (bool) config('sms.reminders_enabled'),
            'sms.reminders_days_before' => (int) config('sms.reminders_days_before', 3),
            'network.provisioner_driver' => (string) config('network.provisioner_driver', 'null'),
            'network.mikrotik_push_enabled' => (bool) config('network.mikrotik_push_enabled', true),
            'network.mikrotik_always_push_ppp_on_customer_save' => (bool) config('network.mikrotik_always_push_ppp_on_customer_save', true),
            'network.radius_push_enabled' => (bool) config('network.radius_push_enabled', true),
            'network.auto_suspend_enabled' => (bool) config('network.auto_suspend_enabled', false),
            'network.service_expiry_enforced' => (bool) config('network.service_expiry_enforced', true),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffIntegrationSnapshot(array $before, array $after): array
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

    /**
     * Normalize Filament Toggle / Livewire booleans. Do not use empty(): string "false" is non-empty but must be OFF.
     */
    private function formStateTruthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === null || $value === '') {
            return false;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
