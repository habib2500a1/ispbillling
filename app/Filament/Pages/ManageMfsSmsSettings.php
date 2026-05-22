<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Support\MobileApkRelease;
use App\Support\MobileAppLinks;
use App\Support\PaymentAdminAccess;
use App\Support\PersonalMfsSetup;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

class ManageMfsSmsSettings extends Page
{
    use HidesHubNavigation;
    use InteractsWithFormActions;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string $view = 'filament.pages.manage-mfs-sms-settings';

    protected static ?string $slug = 'mfs-sms-verify';

    protected static ?string $navigationLabel = 'RCL SMS & apps';

    protected static ?string $title = 'MFS payment SMS verify';

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

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'sms_ingest_enabled' => (bool) config('mfs_personal.sms_ingest.enabled', false),
            'sms_device_key' => '',
            'require_sms_approved' => (bool) config('mfs_personal.sms_ingest.require_sms_approved', false),
            'auto_approve_sms' => (bool) config('mfs_personal.sms_ingest.auto_approve_sms', true),
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
                        Section::make('SMS ingest (bKash / Nagad verify)')
                            ->description('RCL SMS APK বা Admin app থেকে SMS এখানে জমা হবে।')
                            ->schema([
                                Toggle::make('sms_ingest_enabled')
                                    ->label('Enable SMS ingest')
                                    ->helperText('Required for RCL SMS APK and staff SMS forward.'),
                                TextInput::make('sms_device_key')
                                    ->label('Device API key (RCL SMS APK)')
                                    ->password()
                                    ->revealable()
                                    ->dehydrated(false)
                                    ->helperText($this->deviceKeyHint())
                                    ->placeholder('Generate below or paste from .env (MFS_SMS_DEVICE_API_KEY)'),
                                Toggle::make('auto_approve_sms')
                                    ->label('Auto-approve ingested SMS (TrxID auto-verify)')
                                    ->helperText('ON = ক্লায়েন্ট TrxID দিলে সাথে সাথে verify (recommended).')
                                    ->live(),
                                Toggle::make('require_sms_approved')
                                    ->label('Require manual SMS approve in ledger first')
                                    ->helperText('ON হলে TrxID মিললেও admin ledger approve না করলে payment pending থাকবে — সাধারণত OFF রাখুন।')
                                    ->disabled(fn ($get) => (bool) $get('auto_approve_sms')),
                                Placeholder::make('device_api_note')
                                    ->label('Device API')
                                    ->content('Header '.PersonalMfsSetup::adminPanelData()['header_name'].' on POST '.PersonalMfsSetup::deviceIngestUrl().' — full URLs in the panel above.'),
                            ])
                            ->columns(1),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyAutoVerify')
                ->label('Auto-verify setup')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Apply recommended auto-verify?')
                ->modalDescription('SMS ingest ON, auto-approve SMS ON, ledger review OFF, bKash/Nagad auto-verify ON.')
                ->action(fn () => $this->applyRecommendedAutoVerify()),
            Action::make('generateDeviceKey')
                ->label('Generate device key')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generate new device API key?')
                ->modalDescription('Existing MFS Verify phones must be updated with the new key. The key is saved immediately.')
                ->action(fn () => $this->generateDeviceKey()),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save SMS settings')
                ->submit('save'),
        ];
    }

    public function generateDeviceKey(): void
    {
        abort_unless(static::canAccess(), 403);

        $key = Str::random(48);
        AppSetting::putValue('mfs_personal.sms_ingest.api_key', $key);
        AppSetting::putValue('mfs_personal.sms_ingest.enabled', '1');
        AppSetting::syncToRuntimeConfig();

        $this->data['sms_device_key'] = $key;
        $this->data['sms_ingest_enabled'] = true;

        Notification::make()
            ->title('Device key generated & ingest enabled')
            ->body('Copy the key from the form field, then Save. Update every MFS Verify phone.')
            ->success()
            ->send();
    }

    public function applyRecommendedAutoVerify(): void
    {
        abort_unless(static::canAccess(), 403);

        AppSetting::putValue('mfs_personal.sms_ingest.enabled', '1');
        AppSetting::putValue('mfs_personal.sms_ingest.auto_approve_sms', '1');
        AppSetting::putValue('mfs_personal.sms_ingest.require_sms_approved', '0');
        AppSetting::putValue('mfs_personal.gateways.bkash.auto_verify', '1');
        AppSetting::putValue('mfs_personal.gateways.nagad.auto_verify', '1');
        AppSetting::syncToRuntimeConfig();

        $this->form->fill([
            'sms_ingest_enabled' => true,
            'auto_approve_sms' => true,
            'require_sms_approved' => false,
        ]);

        Notification::make()
            ->title('Auto-verify setup applied')
            ->body('MFS Verify APK: API base https://'.parse_url((string) config('app.url'), PHP_URL_HOST).'/api/v1 + device key. Save if you changed the key.')
            ->success()
            ->send();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $autoApprove = (bool) ($state['auto_approve_sms'] ?? false);

        AppSetting::putValue(
            'mfs_personal.sms_ingest.enabled',
            ($state['sms_ingest_enabled'] ?? false) ? '1' : '0',
        );
        AppSetting::putValue(
            'mfs_personal.sms_ingest.auto_approve_sms',
            $autoApprove ? '1' : '0',
        );
        AppSetting::putValue(
            'mfs_personal.sms_ingest.require_sms_approved',
            $autoApprove ? '0' : (($state['require_sms_approved'] ?? false) ? '1' : '0'),
        );

        $rawKey = trim((string) ($this->data['sms_device_key'] ?? ''));
        if ($rawKey !== '') {
            AppSetting::putValue('mfs_personal.sms_ingest.api_key', $rawKey);
        }

        AppSetting::syncToRuntimeConfig();
        $this->data['sms_device_key'] = '';

        Notification::make()->title('MFS SMS settings saved')->success()->send();
    }

    private function deviceKeyHint(): string
    {
        $key = (string) config('mfs_personal.sms_ingest.api_key', '');
        if ($key === '') {
            return 'Not set — enter a long random key, or set MFS_SMS_DEVICE_API_KEY in .env.';
        }

        return 'Current: '.substr($key, 0, 4).'••••'.substr($key, -4).' — leave blank to keep.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'setup' => PersonalMfsSetup::adminPanelData(),
            'downloads' => MobileAppLinks::downloadCards(),
            'mfsApk' => MobileApkRelease::mfsVerify(),
            'bkashPersonal' => \App\Support\PersonalMfsGateway::bkashPersonalEnabled(),
            'nagadPersonal' => \App\Support\PersonalMfsGateway::nagadPersonalEnabled(),
            'bkashAutoVerify' => (bool) config('mfs_personal.gateways.bkash.auto_verify', true),
            'nagadAutoVerify' => (bool) config('mfs_personal.gateways.nagad.auto_verify', true),
            'ledgerUrl' => \App\Filament\Resources\MfsSmsRecordResource::getUrl(),
            'pendingUrl' => \App\Filament\Resources\PendingGatewayPaymentResource::getUrl(),
            'gatewayUrl' => ManagePersonalMfsSettings::getUrl(['tab' => 'bkash']),
            'merchantUrl' => ManagePaymentSettings::getUrl(['gateway' => 'piprapay', 'merchant' => '1']),
        ];
    }
}
