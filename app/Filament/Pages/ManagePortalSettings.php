<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

/**
 * Customer portal behaviour (OTP, etc.) for tenant operators — no payment API secrets here.
 *
 * @property Form $form
 */
class ManagePortalSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string $view = 'filament.pages.manage-portal-settings';

    protected static ?string $navigationLabel = 'Customer portal';

    protected static ?string $title = 'Customer portal settings';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed> | null
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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'portal_otp_enabled' => (bool) config('portal.otp.enabled', false),
            'portal_otp_log_delivery_only' => (bool) config('portal.otp.log_delivery_only', false),
            'portal_otp_ttl_seconds' => (int) config('portal.otp.ttl_seconds', 600),
            'portal_otp_digits' => (int) config('portal.otp.digits', 6),
            'bill_pay_otp_enabled' => (bool) config('bill_payment.otp.enabled', true),
            'bill_pay_otp_log_delivery_only' => (bool) config('bill_payment.otp.log_delivery_only', false),
            'bill_pay_otp_ttl_seconds' => (int) config('bill_payment.otp.ttl_seconds', 600),
            'bill_pay_otp_digits' => (int) config('bill_payment.otp.digits', 6),
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
                        Section::make('Two-step login (OTP)')
                            ->description('After the correct portal password, subscribers can be asked for a one-time code. Codes are sent by email when the account has a valid address, unless “log only” is on (staging: code also appears in laravel.log — never for real production traffic).')
                            ->schema([
                                Toggle::make('portal_otp_enabled')
                                    ->label('Require one-time code after password'),
                                Toggle::make('portal_otp_log_delivery_only')
                                    ->label('Log OTP to application log (staging / diagnostics only)'),
                                TextInput::make('portal_otp_ttl_seconds')
                                    ->label('Code validity (seconds)')
                                    ->numeric()
                                    ->minValue(60)
                                    ->maxValue(3600)
                                    ->required()
                                    ->helperText('Between 60 and 3600. Default 600 (10 minutes).'),
                                TextInput::make('portal_otp_digits')
                                    ->label('Code length (digits)')
                                    ->numeric()
                                    ->minValue(4)
                                    ->maxValue(8)
                                    ->required()
                                    ->helperText('Between 4 and 8 digits. Default 6.'),
                            ])
                            ->columns(1),
                        Section::make('Public bill payment (/pay)')
                            ->description('Client code flow at /pay — same OTP idea as portal login. When off, subscribers go straight to invoice after entering client code. Requires a mobile number on the account when on.')
                            ->schema([
                                Toggle::make('bill_pay_otp_enabled')
                                    ->label('Require mobile verification code (OTP)')
                                    ->helperText('Off = no verify step; client code only.'),
                                Toggle::make('bill_pay_otp_log_delivery_only')
                                    ->label('Log OTP to application log (staging only)')
                                    ->helperText('When on, code is written to laravel.log instead of SMS.'),
                                TextInput::make('bill_pay_otp_ttl_seconds')
                                    ->label('Code validity (seconds)')
                                    ->numeric()
                                    ->minValue(60)
                                    ->maxValue(1800)
                                    ->required(),
                                TextInput::make('bill_pay_otp_digits')
                                    ->label('Code length (digits)')
                                    ->numeric()
                                    ->minValue(4)
                                    ->maxValue(8)
                                    ->required(),
                            ])
                            ->columns(1),
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

        $before = $this->portalSnapshot();

        AppSetting::putValue('portal.otp.enabled', $this->formStateTruthy($state['portal_otp_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('portal.otp.log_delivery_only', $this->formStateTruthy($state['portal_otp_log_delivery_only'] ?? false) ? '1' : '0');
        AppSetting::putValue(
            'portal.otp.ttl_seconds',
            (string) max(60, min(3600, (int) ($state['portal_otp_ttl_seconds'] ?? 600)))
        );
        AppSetting::putValue(
            'portal.otp.digits',
            (string) max(4, min(8, (int) ($state['portal_otp_digits'] ?? 6)))
        );

        AppSetting::putValue('bill_payment.otp.enabled', $this->formStateTruthy($state['bill_pay_otp_enabled'] ?? false) ? '1' : '0');
        AppSetting::putValue('bill_payment.otp.log_delivery_only', $this->formStateTruthy($state['bill_pay_otp_log_delivery_only'] ?? false) ? '1' : '0');
        AppSetting::putValue(
            'bill_payment.otp.ttl_seconds',
            (string) max(60, min(1800, (int) ($state['bill_pay_otp_ttl_seconds'] ?? 600)))
        );
        AppSetting::putValue(
            'bill_payment.otp.digits',
            (string) max(4, min(8, (int) ($state['bill_pay_otp_digits'] ?? 6)))
        );

        AppSetting::syncToRuntimeConfig();

        $after = $this->portalSnapshot();

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Customer portal settings updated',
                'context' => [
                    'diff' => $this->diffSnapshot($before, $after),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('portal_settings.audit_log_failed', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        Notification::make()
            ->title('Portal settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function portalSnapshot(): array
    {
        return [
            'portal.otp.enabled' => (bool) config('portal.otp.enabled', false),
            'portal.otp.log_delivery_only' => (bool) config('portal.otp.log_delivery_only', false),
            'portal.otp.ttl_seconds' => (int) config('portal.otp.ttl_seconds', 600),
            'portal.otp.digits' => (int) config('portal.otp.digits', 6),
            'bill_payment.otp.enabled' => (bool) config('bill_payment.otp.enabled', true),
            'bill_payment.otp.log_delivery_only' => (bool) config('bill_payment.otp.log_delivery_only', false),
            'bill_payment.otp.ttl_seconds' => (int) config('bill_payment.otp.ttl_seconds', 600),
            'bill_payment.otp.digits' => (int) config('bill_payment.otp.digits', 6),
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
