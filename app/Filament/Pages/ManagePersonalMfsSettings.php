<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MfsSmsRecordResource;
use App\Filament\Resources\PendingGatewayPaymentResource;
use App\Models\AppSetting;
use App\Support\BkashSettings;
use App\Support\PaymentAdminAccess;
use App\Support\MobileApkRelease;
use App\Support\PersonalMfsGateway;
use App\Support\PersonalMfsSetup;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * PipraPay-style personal bKash / Nagad — Send Money + TrxID + SMS verify (not merchant API).
 */
class ManagePersonalMfsSettings extends Page
{
    use HidesHubNavigation;
    use InteractsWithFormActions;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.manage-personal-mfs-settings';

    protected static ?string $slug = 'personal-mfs-verify';

    protected static ?string $navigationLabel = 'Personal MFS verify';

    protected static ?string $title = 'Personal bKash / Nagad verify';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 3;

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

        $uiTab = request()->query('tab') === 'nagad' ? 'nagad' : 'bkash';

        $this->form->fill([
            '_ui_tab' => $uiTab,
            'bkash_enabled' => config('bkash.enabled') ? '1' : '0',
            'bkash_personal_number' => (string) config('bkash.personal_number', ''),
            'bkash_personal_name' => (string) config('bkash.personal_name', ''),
            'bkash_personal_instructions' => (string) config('bkash.instructions', ''),
            'bkash_auto_verify' => config('mfs_personal.gateways.bkash.auto_verify', true),
            'nagad_enabled' => config('nagad.enabled') ? '1' : '0',
            'nagad_personal_number' => (string) (config('nagad.personal_number') ?? config('nagad.merchant_number', '')),
            'nagad_personal_name' => (string) config('nagad.personal_name', ''),
            'nagad_personal_instructions' => (string) (config('nagad.instructions') ?? ''),
            'nagad_auto_verify' => config('mfs_personal.gateways.nagad.auto_verify', true),
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
                        Select::make('_ui_tab')
                            ->label('Gateway')
                            ->options([
                                'bkash' => 'bKash Personal',
                                'nagad' => 'Nagad Personal',
                            ])
                            ->live()
                            ->required()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Section::make('Personal bKash (Send Money)')
                            ->description('Merchant bKash API নয় — ক্লায়েন্ট আপনার personal নম্বরে Send Money করবে, TrxID দেবে, SMS match হলে auto verify।')
                            ->icon('heroicon-o-device-phone-mobile')
                            ->schema($this->bkashPersonalSchema())
                            ->visible(fn (Get $get): bool => $get('_ui_tab') !== 'nagad')
                            ->columnSpanFull(),
                        Section::make('Personal Nagad (Send Money)')
                            ->description('Nagad merchant API checkout নয় — personal নম্বর + TrxID + SMS verify।')
                            ->icon('heroicon-o-banknotes')
                            ->schema($this->nagadPersonalSchema())
                            ->visible(fn (Get $get): bool => $get('_ui_tab') === 'nagad')
                            ->columnSpanFull(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function bkashPersonalSchema(): array
    {
        return [
            Radio::make('bkash_enabled')
                ->label('bKash personal payment')
                ->options(['1' => 'Enabled', '0' => 'Disabled'])
                ->inline()
                ->required(),
            TextInput::make('bkash_personal_number')
                ->label('Your bKash number (merchant SIM)')
                ->tel()
                ->placeholder('01XXXXXXXXX')
                ->required(),
            TextInput::make('bkash_personal_name')
                ->label('Display name on /pay page')
                ->maxLength(64),
            Textarea::make('bkash_personal_instructions')
                ->label('Payment instructions')
                ->rows(3)
                ->columnSpanFull(),
            Toggle::make('bkash_auto_verify')
                ->label('Auto-verify when TrxID matches SMS')
                ->helperText('ON + MFS SMS ingest + Auto-approve SMS = payment completes instantly (no pending).')
                ->default(true),
            Placeholder::make('bkash_flow')
                ->label('Verify flow')
                ->content('RCL SMS APK → SMS ledger. ID/PPPoE/registered phone + FIFO bills. Overpay → wallet advance.'),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected function nagadPersonalSchema(): array
    {
        return [
            Radio::make('nagad_enabled')
                ->label('Nagad personal payment')
                ->options(['1' => 'Enabled', '0' => 'Disabled'])
                ->inline()
                ->required(),
            TextInput::make('nagad_personal_number')
                ->label('Your Nagad number')
                ->tel()
                ->placeholder('01XXXXXXXXX')
                ->required(),
            TextInput::make('nagad_personal_name')
                ->label('Display name')
                ->maxLength(64),
            Textarea::make('nagad_personal_instructions')
                ->label('Payment instructions')
                ->rows(3)
                ->columnSpanFull(),
            Toggle::make('nagad_auto_verify')
                ->label('Auto-verify when TrxID matches SMS')
                ->helperText('Same as bKash — requires matching SMS in ledger.')
                ->default(true),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save personal MFS settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        AppSetting::putValue('bkash.gateway_type', BkashSettings::GATEWAY_PERSONAL);
        AppSetting::putValue('bkash.enabled', ($state['bkash_enabled'] ?? '0') === '1' ? '1' : '0');
        AppSetting::putValue('bkash.personal_number', trim((string) ($state['bkash_personal_number'] ?? '')));
        AppSetting::putValue('bkash.personal_name', trim((string) ($state['bkash_personal_name'] ?? '')));
        AppSetting::putValue('bkash.instructions', trim((string) ($state['bkash_personal_instructions'] ?? '')));
        AppSetting::putValue('mfs_personal.gateways.bkash.auto_verify', ($state['bkash_auto_verify'] ?? true) ? '1' : '0');

        AppSetting::putValue('nagad.gateway_type', 'personal');
        AppSetting::putValue('nagad.enabled', ($state['nagad_enabled'] ?? '0') === '1' ? '1' : '0');
        AppSetting::putValue('nagad.personal_number', trim((string) ($state['nagad_personal_number'] ?? '')));
        AppSetting::putValue('nagad.personal_name', trim((string) ($state['nagad_personal_name'] ?? '')));
        AppSetting::putValue('nagad.instructions', trim((string) ($state['nagad.instructions'] ?? '')));
        AppSetting::putValue('mfs_personal.gateways.nagad.auto_verify', ($state['nagad_auto_verify'] ?? true) ? '1' : '0');

        AppSetting::syncToRuntimeConfig();

        Notification::make()
            ->title('Personal MFS settings saved')
            ->body('bKash/Nagad personal mode is active. Configure SMS ingest next.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'setup' => PersonalMfsSetup::adminPanelData(),
            'smsUrl' => ManageMfsSmsSettings::getUrl(),
            'ledgerUrl' => MfsSmsRecordResource::getUrl(),
            'pendingUrl' => PendingGatewayPaymentResource::getUrl(),
            'merchantUrl' => ManagePaymentSettings::getUrl(['gateway' => 'piprapay', 'merchant' => '1']),
            'bkashActive' => PersonalMfsGateway::bkashPersonalEnabled(),
            'nagadActive' => PersonalMfsGateway::nagadPersonalEnabled(),
            'mfsApk' => MobileApkRelease::mfsVerify(),
        ];
    }
}
