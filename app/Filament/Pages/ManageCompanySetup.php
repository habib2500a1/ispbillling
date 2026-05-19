<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Models\AutomaticProcess;
use App\Services\Automation\AutomaticProcessScheduler;
use App\Support\CompanyBranding;
use App\Support\IspTimezone;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Company profile, branding logo, and invoice/receipt layout settings.
 *
 * @property Form $form
 */
class ManageCompanySetup extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static string $view = 'filament.pages.manage-company-setup';

    protected static ?string $navigationLabel = 'Company setup';

    protected static ?string $title = 'Company & invoice setup';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'company-setup';

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

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $logoPath = (string) config('isp.company_logo_path', '');

        $this->form->fill([
            'company_name' => CompanyBranding::name(),
            'company_tagline' => CompanyBranding::tagline(),
            'company_phone' => CompanyBranding::phone(),
            'company_email' => CompanyBranding::email(),
            'company_address' => CompanyBranding::address(),
            'company_website' => CompanyBranding::website(),
            'company_tax_id' => CompanyBranding::taxId(),
            'company_logo' => $logoPath !== '' && Storage::disk('public')->exists($logoPath)
                ? [$logoPath]
                : [],
            'invoice_show_logo' => CompanyBranding::invoiceShowLogo(),
            'invoice_number_prefix' => (string) config('billing.invoice_number_prefix', 'INV'),
            'invoice_number_year_infix' => (bool) config('billing.invoice_number_year_infix', true),
            'invoice_footer' => CompanyBranding::invoiceFooter(),
            'invoice_terms' => CompanyBranding::invoiceTerms(),
            'app_timezone' => IspTimezone::zone(),
            'timezone_label' => IspTimezone::label(),
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
                        Section::make('Company profile')
                            ->description('Shown on admin login, dashboard, customer portal, and printed invoices.')
                            ->schema([
                                TextInput::make('company_name')
                                    ->label('Company name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('company_tagline')
                                    ->label('Tagline / subtitle')
                                    ->maxLength(255),
                                TextInput::make('company_phone')
                                    ->label('Mobile / phone')
                                    ->tel()
                                    ->maxLength(50),
                                TextInput::make('company_email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(255),
                                Textarea::make('company_address')
                                    ->label('Office address')
                                    ->rows(3)
                                    ->maxLength(1000),
                                TextInput::make('company_website')
                                    ->label('Website')
                                    ->url()
                                    ->maxLength(255)
                                    ->placeholder('https://example.com'),
                                TextInput::make('company_tax_id')
                                    ->label('Tax / BIN / VAT registration')
                                    ->maxLength(100),
                            ])
                            ->columns(2),
                        Section::make('Logo & branding')
                            ->schema([
                                FileUpload::make('company_logo')
                                    ->label('Company logo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('company-branding')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->helperText('PNG or JPG, max 2 MB. Used on invoices, receipts, and admin login when enabled below.'),
                                Toggle::make('invoice_show_logo')
                                    ->label('Show logo on invoice & payment receipt PDFs')
                                    ->default(true),
                            ]),
                        Section::make('Timezone & schedules')
                            ->description('Billing runs, automatic processes, and admin dates use this timezone. Current server time: '.IspTimezone::nowFormatted('Y-m-d g:i A').'.')
                            ->schema([
                                Select::make('app_timezone')
                                    ->label('Timezone')
                                    ->options(IspTimezone::optionsForSelect())
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->helperText('Bangladesh ISPs: Asia/Dhaka (BDT).'),
                                TextInput::make('timezone_label')
                                    ->label('Display label')
                                    ->maxLength(12)
                                    ->default('BDT')
                                    ->helperText('Short label on automatic process & reports, e.g. BDT, IST.'),
                                Placeholder::make('timezone_preview')
                                    ->label('Preview')
                                    ->content(function (Get $get): string {
                                        $zone = (string) ($get('app_timezone') ?: IspTimezone::zone());
                                        $label = (string) ($get('timezone_label') ?: IspTimezone::label());

                                        return sprintf(
                                            '%s (%s) — now: %s',
                                            $label,
                                            $zone,
                                            now($zone)->format('Y-m-d g:i A'),
                                        );
                                    }),
                            ])
                            ->columns(2),
                        Section::make('Invoice numbering')
                            ->description('New invoices use this prefix. Existing invoice numbers are not changed.')
                            ->schema([
                                TextInput::make('invoice_number_prefix')
                                    ->label('Invoice prefix')
                                    ->required()
                                    ->maxLength(20)
                                    ->helperText('Example: INV → INV-2026-00001'),
                                Toggle::make('invoice_number_year_infix')
                                    ->label('Include year in invoice number')
                                    ->helperText('When on: PREFIX-YEAR-SEQUENCE. When off: PREFIX-SEQUENCE only.'),
                            ])
                            ->columns(2),
                        Section::make('Invoice footer & terms')
                            ->schema([
                                Textarea::make('invoice_footer')
                                    ->label('Footer message')
                                    ->rows(2)
                                    ->maxLength(500),
                                Textarea::make('invoice_terms')
                                    ->label('Terms & conditions (optional)')
                                    ->rows(4)
                                    ->maxLength(2000)
                                    ->helperText('Printed below totals on invoice PDF.'),
                            ]),
                        Section::make('Staff login (reference)')
                            ->description('Change your password: top-right avatar → My account. Reset other staff: Admin & staff → Staff users.')
                            ->schema([
                                Placeholder::make('bootstrap_admin_email')
                                    ->label('Bootstrap admin email (.env)')
                                    ->content(fn (): string => (string) config('isp.admin_email', '—'))
                                    ->visible(fn (): bool => auth()->user()?->hasRole('super-admin') ?? false),
                                Placeholder::make('bootstrap_admin_password_hint')
                                    ->label('Bootstrap admin password')
                                    ->content('Set in server .env as ISP_ADMIN_PASSWORD — not stored in the database. Change after first login.')
                                    ->visible(fn (): bool => auth()->user()?->hasRole('super-admin') ?? false),
                            ])
                            ->visible(fn (): bool => auth()->user()?->hasRole('super-admin') ?? false),
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
                ->label('Save company setup')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $before = $this->snapshot();

        AppSetting::putValue('isp.company_name', trim((string) $state['company_name']));
        AppSetting::putValue('isp.company_tagline', trim((string) ($state['company_tagline'] ?? '')));
        AppSetting::putValue('isp.company_phone', trim((string) ($state['company_phone'] ?? '')));
        AppSetting::putValue('isp.company_email', trim((string) ($state['company_email'] ?? '')));
        AppSetting::putValue('isp.company_address', trim((string) ($state['company_address'] ?? '')));
        AppSetting::putValue('isp.company_website', trim((string) ($state['company_website'] ?? '')));
        AppSetting::putValue('isp.company_tax_id', trim((string) ($state['company_tax_id'] ?? '')));

        $timezone = trim((string) ($state['app_timezone'] ?? ''));
        if ($timezone === '' || ! IspTimezone::isValidZone($timezone)) {
            Notification::make()
                ->title('Invalid timezone')
                ->body('Choose a valid timezone from the list.')
                ->danger()
                ->send();

            return;
        }

        AppSetting::putValue('app.timezone', $timezone);
        AppSetting::putValue('isp.timezone_label', strtoupper(trim((string) ($state['timezone_label'] ?? 'BDT'))) ?: 'BDT');

        AppSetting::putValue('isp.invoice_show_logo', $this->formStateTruthy($state['invoice_show_logo'] ?? true) ? '1' : '0');
        AppSetting::putValue('isp.invoice_footer', trim((string) ($state['invoice_footer'] ?? '')));
        AppSetting::putValue('isp.invoice_terms', trim((string) ($state['invoice_terms'] ?? '')));

        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($state['invoice_number_prefix'] ?? 'INV')) ?: 'INV');
        AppSetting::putValue('billing.invoice_number_prefix', $prefix);
        AppSetting::putValue(
            'billing.invoice_number_year_infix',
            $this->formStateTruthy($state['invoice_number_year_infix'] ?? true) ? '1' : '0'
        );

        $logo = $state['company_logo'] ?? null;
        $logoPath = is_array($logo) ? ($logo[0] ?? null) : $logo;
        if (filled($logoPath)) {
            AppSetting::putValue('isp.company_logo_path', (string) $logoPath);
        } else {
            AppSetting::query()->where('key', 'isp.company_logo_path')->delete();
            AppSetting::restoreConfigKeyFromEnv('isp.company_logo_path');
        }

        AppSetting::syncToRuntimeConfig();

        if (Schema::hasTable('automatic_processes')) {
            $scheduler = app(AutomaticProcessScheduler::class);
            AutomaticProcess::query()->withoutGlobalScopes()->each(function (AutomaticProcess $process) use ($scheduler): void {
                $process->forceFill(['next_run_at' => $scheduler->computeNextRunAt($process)])->save();
            });
        }

        $after = $this->snapshot();

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Company & invoice setup updated',
                'context' => [
                    'diff' => $this->diffSnapshot($before, $after),
                    'logo_updated' => filled($logoPath),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('company_setup.audit_log_failed', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        Notification::make()
            ->title('Company setup saved')
            ->body('Branding and invoice settings apply immediately to new PDFs and the admin panel.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'isp.company_name' => CompanyBranding::name(),
            'isp.company_phone' => CompanyBranding::phone(),
            'isp.company_email' => CompanyBranding::email(),
            'isp.invoice_show_logo' => CompanyBranding::invoiceShowLogo(),
            'billing.invoice_number_prefix' => (string) config('billing.invoice_number_prefix', 'INV'),
            'billing.invoice_number_year_infix' => (bool) config('billing.invoice_number_year_infix', true),
            'isp.company_logo_path_set' => filled(config('isp.company_logo_path')),
            'app.timezone' => IspTimezone::zone(),
            'isp.timezone_label' => IspTimezone::label(),
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
