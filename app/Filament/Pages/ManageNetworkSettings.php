<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MikrotikServerResource;
use App\Models\AppSetting;
use App\Models\IntegrationSettingsAudit;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Network\NetworkSettingsConfigurator;
use App\Services\Radius\RadiusAccountingService;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;

/**
 * @property Form $form
 */
class ManageNetworkSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static string $view = 'filament.pages.manage-network-settings';

    protected static ?string $slug = 'network-settings';

    protected static ?string $navigationLabel = 'API & RADIUS setup';

    protected static ?string $title = 'MikroTik API & RADIUS setup';

    protected static ?string $navigationGroup = 'Network';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super-admin') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(app(NetworkSettingsConfigurator::class)->formDefaults());
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
                        Tabs::make('network')
                            ->tabs([
                                Tab::make('Quick setup')
                                    ->id('quick')
                                    ->icon('heroicon-o-bolt')
                                    ->schema($this->quickSetupSchema()),
                                Tab::make('MikroTik API')
                                    ->id('mikrotik')
                                    ->icon('heroicon-o-server')
                                    ->schema($this->mikrotikSchema()),
                                Tab::make('RADIUS')
                                    ->id('radius')
                                    ->icon('heroicon-o-circle-stack')
                                    ->schema($this->radiusSchema()),
                            ])
                            ->persistTabInQueryString('tab'),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function quickSetupSchema(): array
    {
        return [
            Section::make('One-click mode')
                ->description('Choose how online users and PPP sync work. You can fine-tune each tab after selecting a mode.')
                ->schema([
                    Radio::make('network_setup_mode')
                        ->label('Setup mode')
                        ->options(NetworkSettingsConfigurator::modeLabels())
                        ->descriptions([
                            NetworkSettingsConfigurator::MODE_OFF => 'Stops bandwidth collect, router poll, and PPP push.',
                            NetworkSettingsConfigurator::MODE_MIKROTIK => 'RouterOS API only — add routers under Routers list.',
                            NetworkSettingsConfigurator::MODE_RADIUS => 'FreeRADIUS radacct only — fill RADIUS DB tab.',
                            NetworkSettingsConfigurator::MODE_BOTH => 'Best for most ISPs: API + accounting merged.',
                        ])
                        ->live()
                        ->columnSpanFull(),
                    Placeholder::make('live_status')
                        ->label('Current status')
                        ->content(fn (): string => $this->statusSummary())
                        ->columnSpanFull(),
                ]),
            Section::make('Shortcuts')
                ->schema([
                    Placeholder::make('routers_link')
                        ->label('MikroTik routers')
                        ->content(fn (): string => 'Add API host, user, password: '.MikrotikServerResource::getUrl('index')),
                    Placeholder::make('bandwidth_link')
                        ->label('Online / bandwidth')
                        ->content(fn (): string => 'Monitor: '.BandwidthMonitor::getUrl()),
                ])
                ->columns(2),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function mikrotikSchema(): array
    {
        return [
            Section::make('MikroTik API switches')
                ->schema([
                    Toggle::make('bandwidth_collection_enabled')
                        ->label('Collect online users & bandwidth')
                        ->helperText('Runs isp:collect-bandwidth (scheduler). Required for accurate Online column.'),
                    Toggle::make('mikrotik_poll_enabled')
                        ->label('Poll router health (API up/down)')
                        ->helperText('Every 2 minutes — updates last_api_status on each server.'),
                    Toggle::make('network_mikrotik_push_enabled')
                        ->label('Push PPP secrets to MikroTik (suspend / unsuspend)')
                        ->live(),
                    Toggle::make('network_mikrotik_always_push_ppp_on_customer_save')
                        ->label('Push PPP on every customer save')
                        ->visible(fn (Get $get): bool => (bool) $get('network_mikrotik_push_enabled')),
                    Toggle::make('network_auto_suspend_enabled')
                        ->label('Auto suspend on overdue invoice'),
                    Toggle::make('network_service_expiry_enforced')
                        ->label('Auto off when service expires'),
                ])
                ->columns(1),
            Section::make('Routers in panel')
                ->schema([
                    Placeholder::make('router_count')
                        ->label('Enabled routers')
                        ->content(function (): string {
                            $tenantId = TenantResolver::currentTenantId() ?? 1;
                            $total = MikrotikServer::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
                            $enabled = MikrotikServer::query()->withoutGlobalScopes()
                                ->where('tenant_id', $tenantId)->where('is_enabled', true)->count();

                            return "{$enabled} enabled / {$total} total — ".MikrotikServerResource::getUrl('index');
                        }),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function radiusSchema(): array
    {
        return [
            Section::make('RADIUS accounting (radacct)')
                ->schema([
                    Toggle::make('radius_accounting_enabled')
                        ->label('Enable RADIUS accounting sync')
                        ->live()
                        ->helperText('Reads active sessions from radacct for online list & bandwidth.'),
                    Toggle::make('radius_merge_with_api')
                        ->label('Merge with MikroTik API')
                        ->visible(fn (Get $get): bool => (bool) $get('radius_accounting_enabled'))
                        ->helperText('When both are on, prefers API rates and merges byte counters.'),
                    Toggle::make('network_radius_push_enabled')
                        ->label('Push customers to RADIUS (radcheck / groups)')
                        ->live(),
                    Toggle::make('radius_admin_enabled')
                        ->label('Show RADIUS users admin in Network menu')
                        ->visible(fn (Get $get): bool => (bool) $get('network_radius_push_enabled')),
                ])
                ->columns(1),
            Section::make('RADIUS database')
                ->description('Saved here (encrypted). Leave password blank to keep current. .env RADIUS_DB_* is fallback when empty.')
                ->schema([
                    TextInput::make('radius_db_host')
                        ->label('DB host')
                        ->maxLength(255)
                        ->default('127.0.0.1'),
                    TextInput::make('radius_db_port')
                        ->label('DB port')
                        ->numeric()
                        ->default(3306)
                        ->minValue(1)
                        ->maxValue(65535),
                    TextInput::make('radius_db_database')
                        ->label('Database name')
                        ->default('radius'),
                    TextInput::make('radius_db_username')
                        ->label('Username')
                        ->default('radius'),
                    TextInput::make('radius_db_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Only type to change password.'),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => (bool) $get('radius_accounting_enabled')),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('testMikrotik')
                ->label('Test MikroTik API')
                ->color('gray')
                ->icon('heroicon-o-signal')
                ->action('runMikrotikTest'),
            Action::make('testRadius')
                ->label('Test RADIUS DB')
                ->color('gray')
                ->icon('heroicon-o-circle-stack')
                ->action('runRadiusTest'),
            Action::make('save')
                ->label('Save settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function runMikrotikTest(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! (bool) config('mikrotik.poll_enabled', true)) {
            Notification::make()->title('MikroTik poll is OFF in settings')->warning()->send();

            return;
        }

        $tenantId = TenantResolver::currentTenantId() ?? 1;
        $stats = app(MikrotikFleetCoordinator::class)->probeAllServers($tenantId);

        Notification::make()
            ->title('MikroTik API test')
            ->body("Polled {$stats['polled']} router(s): {$stats['online']} online, {$stats['offline']} offline.")
            ->success()
            ->send();
    }

    public function runRadiusTest(): void
    {
        abort_unless(static::canAccess(), 403);

        AppSetting::syncToRuntimeConfig();
        $ping = app(RadiusAccountingService::class)->ping(TenantResolver::currentTenantId());

        if ($ping['ok'] ?? false) {
            Notification::make()
                ->title('RADIUS DB connected')
                ->body('Active sessions (global): '.($ping['active_sessions'] ?? 0)
                    .' · tenant-scoped: '.($ping['tenant_active_sessions'] ?? 'n/a'))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('RADIUS connection failed')
            ->body((string) ($ping['message'] ?? 'Unknown error'))
            ->danger()
            ->send();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        app(NetworkSettingsConfigurator::class)->persistFromForm($state);

        $this->data['radius_db_password'] = '';

        try {
            IntegrationSettingsAudit::query()->create([
                'user_id' => auth()->id(),
                'ip_address' => request()->ip(),
                'summary' => 'Network API & RADIUS settings updated',
                'context' => ['mode' => $state['network_setup_mode'] ?? null],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('network_settings.audit_failed', ['message' => $e->getMessage()]);
        }

        Notification::make()->title('Network settings saved')->success()->send();
    }

    private function statusSummary(): string
    {
        $parts = [];
        $parts[] = 'Mode: '.NetworkSettingsConfigurator::modeLabels()[app(NetworkSettingsConfigurator::class)->detectCurrentMode()] ?? '?';
        $parts[] = 'MikroTik collect: '.(config('bandwidth.collection_enabled') ? 'ON' : 'OFF');
        $parts[] = 'RADIUS accounting: '.(config('radius.accounting_enabled') ? 'ON' : 'OFF');
        if (config('radius.accounting_enabled')) {
            $parts[] = 'Merge API+RADIUS: '.(config('radius.merge_with_api') ? 'ON' : 'OFF');
        }

        return implode(' · ', $parts);
    }
}
