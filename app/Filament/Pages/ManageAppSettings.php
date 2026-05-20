<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
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

    protected static bool $shouldRegisterNavigation = false;

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
                        Section::make('MikroTik API & RADIUS')
                            ->description('Easy on/off, DB credentials, and connection tests — same style as Payment gateways.')
                            ->schema([
                                Placeholder::make('network_settings_link')
                                    ->label('Open network setup')
                                    ->content(fn (): string => 'Configure at: '.\App\Filament\Pages\ManageNetworkSettings::getUrl()),
                            ]),
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
