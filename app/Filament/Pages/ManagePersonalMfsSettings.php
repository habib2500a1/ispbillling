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
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

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

    protected static ?string $navigationLabel = 'bKash Personal / Nagad Personal';

    protected static ?string $title = 'bKash Personal / Nagad Personal';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 3;

    /** Active gateway tab — synced to ?tab=bkash|nagad (survives Livewire save POST). */
    #[Url(as: 'tab', except: 'bkash')]
    public string $activeGatewayTab = 'bkash';

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

        $this->activeGatewayTab = $this->activeGatewayTab === 'nagad' ? 'nagad' : 'bkash';

        $this->form->fill([
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
                        Tabs::make('personal_mfs_gateways')
                            ->activeTab(fn (): int => $this->activeGatewayTab === 'nagad' ? 2 : 1)
                            ->persistTabInQueryString('tab')
                            ->tabs([
                                Tab::make('bKash Personal')
                                    ->id('bkash')
                                    ->icon('heroicon-o-device-phone-mobile')
                                    ->schema($this->bkashPersonalSchema()),
                                Tab::make('Nagad Personal')
                                    ->id('nagad')
                                    ->icon('heroicon-o-banknotes')
                                    ->schema($this->nagadPersonalSchema()),
                            ])
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

    protected function activeUiTab(): string
    {
        return $this->activeGatewayTab === 'nagad' ? 'nagad' : 'bkash';
    }

    protected function getFormActions(): array
    {
        $tab = $this->activeUiTab();

        return [
            Action::make('save')
                ->label($tab === 'nagad' ? 'Save Nagad settings' : 'Save bKash settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $tab = $this->activeUiTab();
        $state = $this->form->getState();

        if ($tab === 'nagad') {
            $this->persistNagadSettings($state);
            $label = 'Nagad personal';
        } else {
            $this->persistBkashSettings($state);
            $label = 'bKash personal';
        }

        AppSetting::syncPublicPaymentGatewayFlags();

        Notification::make()
            ->title($label.' settings saved')
            ->body('bKash ও Nagad আলাদা — অন্য gateway-এর ON/OFF অপরিবর্তিত।')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persistBkashSettings(array $state): void
    {
        AppSetting::putValues([
            'bkash.gateway_type' => BkashSettings::GATEWAY_PERSONAL,
            'bkash.enabled' => $this->resolveEnabledFlag($state, 'bkash_enabled', 'bkash.enabled') ? '1' : '0',
            'bkash.personal_number' => trim((string) ($state['bkash_personal_number'] ?? config('bkash.personal_number', ''))),
            'bkash.personal_name' => trim((string) ($state['bkash_personal_name'] ?? config('bkash.personal_name', ''))),
            'bkash.instructions' => trim((string) ($state['bkash_personal_instructions'] ?? config('bkash.instructions', ''))),
            'mfs_personal.gateways.bkash.auto_verify' => $this->resolveToggleFlag($state, 'bkash_auto_verify', 'mfs_personal.gateways.bkash.auto_verify') ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persistNagadSettings(array $state): void
    {
        AppSetting::putValues([
            'nagad.gateway_type' => 'personal',
            'nagad.enabled' => $this->resolveEnabledFlag($state, 'nagad_enabled', 'nagad.enabled') ? '1' : '0',
            'nagad.personal_number' => trim((string) ($state['nagad_personal_number'] ?? config('nagad.personal_number', ''))),
            'nagad.personal_name' => trim((string) ($state['nagad_personal_name'] ?? config('nagad.personal_name', ''))),
            'nagad.instructions' => trim((string) ($state['nagad_personal_instructions'] ?? config('nagad.instructions', ''))),
            'mfs_personal.gateways.nagad.auto_verify' => $this->resolveToggleFlag($state, 'nagad_auto_verify', 'mfs_personal.gateways.nagad.auto_verify') ? '1' : '0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveEnabledFlag(array $state, string $stateKey, string $configKey): bool
    {
        if (array_key_exists($stateKey, $state)) {
            return ($state[$stateKey] ?? '0') === '1' || $state[$stateKey] === true;
        }

        return (bool) config($configKey, false);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function resolveToggleFlag(array $state, string $stateKey, string $configKey): bool
    {
        if (array_key_exists($stateKey, $state)) {
            return (bool) ($state[$stateKey] ?? false);
        }

        return (bool) config($configKey, true);
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
            'activeTab' => $this->activeGatewayTab,
        ];
    }
}
