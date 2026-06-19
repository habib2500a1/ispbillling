<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\Search\CustomerSearchConfigurator;
use App\Services\Search\CustomerSearchHealthService;
use App\Support\CustomerSearchSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
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
class ManageCustomerSearchSettings extends Page
{
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static string $view = 'filament.pages.manage-customer-search-settings';

    protected static ?string $navigationLabel = 'Customer search';

    protected static ?string $title = 'Customer search (Meilisearch)';

    protected static ?string $slug = 'customer-search-settings';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $health = [];

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

        $this->refreshHealth();

        $this->form->fill([
            'enabled' => CustomerSearchSettings::enabled(),
            'meilisearch_host' => (string) config('customer_search.meilisearch_host', '') ?: CustomerSearchSettings::detectDefaultHost(),
            'sql_fallback' => CustomerSearchSettings::sqlFallback(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getForms(): array
    {
        $health = $this->health;
        $snapshot = CustomerSearchSettings::dashboardSnapshot();

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Section::make('Status')
                            ->schema([
                                Placeholder::make('health_status')
                                    ->label('Engine')
                                    ->content(new HtmlString(
                                        '<span class="font-semibold">'.e($health['message'] ?? '—').'</span>'
                                        .' · Host <code class="text-xs">'.e($health['host'] ?? '—').'</code>'
                                        .($health['latency_ms'] ? ' · '.$health['latency_ms'].' ms' : '')
                                    )),
                                Placeholder::make('key_info')
                                    ->label('Security key')
                                    ->content(new HtmlString(
                                        'Auto from <strong>APP_KEY</strong> ('.e($snapshot['key_source'] ?? '—').')'
                                        .' · preview <code class="text-xs">'.e($snapshot['master_key_preview'] ?? '—').'</code>'
                                        .'<br><span class="text-xs text-gray-500">No separate Meilisearch key in .env needed.</span>'
                                    )),
                            ]),
                        Section::make('Settings')
                            ->description('Ticket create, bill collection, mobile staff search, and Ctrl+K palette use this engine.')
                            ->schema([
                                Toggle::make('enabled')
                                    ->label('Fast search (Meilisearch)')
                                    ->helperText('Off = PostgreSQL LIKE search only (slower at 500k scale).')
                                    ->default(true),
                                TextInput::make('meilisearch_host')
                                    ->label('Meilisearch URL')
                                    ->helperText('Docker default: http://meilisearch:7700 · leave as detected unless custom.')
                                    ->maxLength(255),
                                Toggle::make('sql_fallback')
                                    ->label('SQL fallback when Meilisearch offline')
                                    ->default(true),
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
            Action::make('reindex')
                ->label('Re-index all subscribers')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->reindexAll()),
            Action::make('refresh')
                ->label('Refresh status')
                ->color('gray')
                ->action(fn () => $this->refreshHealth()),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        AppSetting::putValue('customer_search.enabled', $this->truthy($state['enabled'] ?? true) ? '1' : '0');
        AppSetting::putValue('customer_search.sql_fallback', $this->truthy($state['sql_fallback'] ?? true) ? '1' : '0');

        $host = trim((string) ($state['meilisearch_host'] ?? ''));
        if ($host === '' || $host === CustomerSearchSettings::detectDefaultHost()) {
            AppSetting::query()->where('key', 'customer_search.meilisearch_host')->delete();
            AppSetting::restoreConfigKeyFromEnv('customer_search.meilisearch_host');
        } else {
            AppSetting::putValue('customer_search.meilisearch_host', rtrim($host, '/'));
        }

        AppSetting::syncToRuntimeConfig();
        CustomerSearchConfigurator::apply();
        $this->refreshHealth();

        Notification::make()
            ->title('Customer search settings saved')
            ->success()
            ->send();
    }

    public function reindexAll(): void
    {
        abort_unless(static::canAccess(), 403);

        CustomerSearchConfigurator::apply();

        if (! app(CustomerSearchHealthService::class)->isHealthy()) {
            Notification::make()
                ->title('Meilisearch not reachable')
                ->body('Start the meilisearch container, then try again. SQL fallback remains active.')
                ->danger()
                ->send();

            return;
        }

        try {
            Artisan::call('isp:scout-sync-customers', ['--fresh' => true]);
            CustomerSearchSettings::markIndexBootstrapped();
            AppSetting::putValue('customer_search.last_sync_at', now()->toIso8601String());
            $this->refreshHealth();

            Notification::make()
                ->title('Subscriber index rebuilt')
                ->body(trim(Artisan::output()) ?: 'Meilisearch index is ready.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Re-index failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshHealth(): void
    {
        CustomerSearchConfigurator::apply();
        $this->health = app(CustomerSearchHealthService::class)->status();
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }
}
