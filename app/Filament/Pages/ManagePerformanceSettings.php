<?php

namespace App\Filament\Pages;

use App\Filament\Pages\ManageCustomerSearchSettings;
use App\Models\AppSetting;
use App\Services\Search\CustomerSearchHealthService;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;

/**
 * @property Form $form
 */
class ManagePerformanceSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static string $view = 'filament.pages.manage-performance-settings';

    protected static ?string $navigationLabel = 'Performance';

    protected static ?string $title = 'Performance & polling';

    protected static ?string $slug = 'performance-settings';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $searchHealth = [];

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

        $this->refreshSearchHealth();

        $this->form->fill([
            'auto_sync_on_customer_view' => (bool) config('optical.auto_sync_on_customer_view', false),
            'auto_sync_on_customer_save' => (bool) config('optical.auto_sync_on_customer_save', true),
            'legacy_portal_auto_sync' => (bool) config('optical.legacy_portal_auto_sync', true),
            'auto_sync_olt_on_mac_lookup' => (bool) config('optical.auto_sync_olt_on_mac_lookup', true),
            'customer_sync_connection' => (string) config('optical.customer_sync_connection', 'redis'),
            'optical_poll_interval' => (int) config('optical.poll_interval_minutes', 10),
            'bandwidth_poll_interval' => (int) config('bandwidth.poll_interval_minutes', 5),
            'mikrotik_poll_enabled' => (bool) config('mikrotik.poll_enabled', true),
            'mikrotik_fetch_details_poll' => (bool) config('mikrotik.fetch_details_poll_enabled', false),
            'olt_snmp_poll_enabled' => (bool) config('network.olt_snmp_poll_enabled', true),
            'sync_fast_mode' => (bool) config('sync.fast_mode', true),
            'bundle_css' => (bool) config('isp.assets.bundle_css', false),
            'app_settings_cache_seconds' => (int) config('isp.app_settings_sync_cache_seconds', 120),
            'max_runner_processes' => (int) config('automation.max_runner_processes', 1),
            'runner_lock_seconds' => (int) config('automation.runner_lock_seconds', 1800),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        $search = $this->searchHealth;

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Subscriber page speed')
                            ->description('Keep OFF “sync on view” for 1–2s subscriber loads at 500k scale. Use scheduler + Retest ONU instead.')
                            ->schema([
                                Toggle::make('auto_sync_on_customer_view')
                                    ->label('Auto OLT sync when opening subscriber profile')
                                    ->helperText('SNMP on every view — slow. Recommended: OFF'),
                                Toggle::make('auto_sync_on_customer_save')
                                    ->label('Queue OLT sync after customer save')
                                    ->default(true),
                                Toggle::make('legacy_portal_auto_sync')
                                    ->label('Legacy portal optical auto-sync')
                                    ->default(true),
                                Toggle::make('auto_sync_olt_on_mac_lookup')
                                    ->label('OLT sync on MAC lookup miss')
                                    ->default(true),
                                Select::make('customer_sync_connection')
                                    ->label('Optical sync queue')
                                    ->options([
                                        'redis' => 'Redis queue (recommended — needs Horizon)',
                                        'sync' => 'Inline sync (no worker — blocks save)',
                                        'database' => 'Database queue',
                                    ])
                                    ->default('redis')
                                    ->required(),
                            ])
                            ->columns(1),
                        Section::make('Background polling')
                            ->description('Higher intervals = less server load. Lower = fresher data.')
                            ->schema([
                                TextInput::make('bandwidth_poll_interval')
                                    ->label('Bandwidth / PPP poll (minutes)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(60)
                                    ->required(),
                                TextInput::make('optical_poll_interval')
                                    ->label('ONU signal poll (minutes)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(120)
                                    ->required(),
                                Toggle::make('mikrotik_poll_enabled')
                                    ->label('MikroTik status poll')
                                    ->default(true),
                                Toggle::make('mikrotik_fetch_details_poll')
                                    ->label('MikroTik fetch-details poll (heavy — keep OFF)')
                                    ->helperText('Hourly API snapshot — only enable for debugging.')
                                    ->default(false),
                                Toggle::make('olt_snmp_poll_enabled')
                                    ->label('OLT SNMP intelligence poll')
                                    ->default(true),
                            ])
                            ->columns(2),
                        Section::make('Platform & panel speed')
                            ->schema([
                                Toggle::make('sync_fast_mode')
                                    ->label('Fast sync mode (batch DB + smart MikroTik/OLT)')
                                    ->default(true),
                                Toggle::make('bundle_css')
                                    ->label('Bundle admin CSS (fewer HTTP requests)')
                                    ->helperText('After enabling, run “Rebuild CSS bundles” once.')
                                    ->default(true),
                                TextInput::make('app_settings_cache_seconds')
                                    ->label('Settings cache TTL (seconds)')
                                    ->numeric()
                                    ->minValue(30)
                                    ->maxValue(600)
                                    ->helperText('How long decrypted app_settings stay in memory cache.')
                                    ->required(),
                            ])
                            ->columns(2),
                        Section::make('Scheduler safety')
                            ->description('Prevents stacked cron jobs from exhausting PHP-FPM (502 errors).')
                            ->schema([
                                TextInput::make('max_runner_processes')
                                    ->label('Max concurrent automation runners')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4)
                                    ->required(),
                                TextInput::make('runner_lock_seconds')
                                    ->label('Automation lock duration (seconds)')
                                    ->numeric()
                                    ->minValue(60)
                                    ->maxValue(7200)
                                    ->required(),
                            ])
                            ->columns(2),
                        Section::make('Customer search')
                            ->schema([
                                Placeholder::make('search_status')
                                    ->label('Meilisearch')
                                    ->content(new HtmlString(
                                        '<span class="font-medium">'.e($search['message'] ?? '—').'</span>'
                                        .' · <a class="underline text-primary-600" href="'.e(ManageCustomerSearchSettings::getUrl()).'">Open search settings →</a>'
                                    )),
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
                ->label('Save performance settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('warm_caches')
                ->label('Warm dashboard caches')
                ->icon('heroicon-o-fire')
                ->color('gray')
                ->action(fn () => $this->warmCaches()),
            Action::make('rebuild_css')
                ->label('Rebuild CSS bundles')
                ->icon('heroicon-o-paint-brush')
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->rebuildCss()),
            Action::make('reset_defaults')
                ->label('Reset to recommended defaults')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->resetDefaults()),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        AppSetting::putValues([
            'optical.auto_sync_on_customer_view' => $this->truthy($state['auto_sync_on_customer_view'] ?? false) ? '1' : '0',
            'optical.auto_sync_on_customer_save' => $this->truthy($state['auto_sync_on_customer_save'] ?? true) ? '1' : '0',
            'optical.legacy_portal_auto_sync' => $this->truthy($state['legacy_portal_auto_sync'] ?? true) ? '1' : '0',
            'optical.auto_sync_olt_on_mac_lookup' => $this->truthy($state['auto_sync_olt_on_mac_lookup'] ?? true) ? '1' : '0',
            'optical.customer_sync_connection' => (string) ($state['customer_sync_connection'] ?? 'redis'),
            'optical.poll_interval_minutes' => (string) max(1, min(120, (int) ($state['optical_poll_interval'] ?? 10))),
            'bandwidth.poll_interval_minutes' => (string) max(1, min(60, (int) ($state['bandwidth_poll_interval'] ?? 5))),
            'mikrotik.poll_enabled' => $this->truthy($state['mikrotik_poll_enabled'] ?? true) ? '1' : '0',
            'mikrotik.fetch_details_poll_enabled' => $this->truthy($state['mikrotik_fetch_details_poll'] ?? false) ? '1' : '0',
            'network.olt_snmp_poll_enabled' => $this->truthy($state['olt_snmp_poll_enabled'] ?? true) ? '1' : '0',
            'sync.fast_mode' => $this->truthy($state['sync_fast_mode'] ?? true) ? '1' : '0',
            'isp.assets.bundle_css' => $this->truthy($state['bundle_css'] ?? true) ? '1' : '0',
            'isp.app_settings_sync_cache_seconds' => (string) max(30, min(600, (int) ($state['app_settings_cache_seconds'] ?? 120))),
            'automation.max_runner_processes' => (string) max(1, min(4, (int) ($state['max_runner_processes'] ?? 1))),
            'automation.runner_lock_seconds' => (string) max(60, min(7200, (int) ($state['runner_lock_seconds'] ?? 1800))),
        ]);

        Notification::make()
            ->title('Performance settings saved')
            ->body('Changes apply immediately. Reload PHP-FPM if opcache caches old config.')
            ->success()
            ->send();
    }

    public function warmCaches(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('isp:warm-dashboard-caches');

        Notification::make()
            ->title('Dashboard caches warmed')
            ->success()
            ->send();
    }

    public function rebuildCss(): void
    {
        abort_unless(static::canAccess(), 403);

        Artisan::call('isp:build-styles');

        Notification::make()
            ->title('CSS bundles rebuilt')
            ->body('Ensure “Bundle admin CSS” is enabled above.')
            ->success()
            ->send();
    }

    public function resetDefaults(): void
    {
        abort_unless(static::canAccess(), 403);

        $defaults = config('performance.env_defaults', []);
        $keys = array_keys($defaults);

        AppSetting::query()->whereIn('key', $keys)->delete();

        foreach ($keys as $key) {
            AppSetting::restoreConfigKeyFromEnv($key);
        }

        AppSetting::syncToRuntimeConfig();
        $this->mount();

        Notification::make()
            ->title('Reset to recommended performance defaults')
            ->success()
            ->send();
    }

    public function refreshSearchHealth(): void
    {
        if (class_exists(CustomerSearchHealthService::class)) {
            $this->searchHealth = app(CustomerSearchHealthService::class)->status();
        }
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }
}
