<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Filament\Pages\ClientsHub;
use App\Filament\Pages\OnlineClientsMonitoring;
use App\Filament\Resources\CustomerResource;
use App\Models\Zone;
use App\Services\Billing\BillingAccountListCounts;
use App\Services\Clients\ClientsDashboardService;
use App\Services\Mobile\StaffBillingKpiResolver;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Shared Sheba-Fi–style client directory (filters, columns, bulk bar).
 */
trait UsesClientsDirectoryLayout
{
    use AppliesClientsDirectoryTableQuery;

    public function bootUsesClientsDirectoryLayout(): void
    {
        $this->loadDirectoryChrome();
    }

    public function updatedTableSearch(): void
    {
        if ($this->getTable()->persistsSearchInSession()) {
            session()->put($this->getTableSearchSessionKey(), $this->tableSearch);
        }

        if ($this->getTable()->shouldDeselectAllRecordsWhenFiltered()) {
            $this->deselectAllTableRecords();
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function updatedTableFilters(): void
    {
        if ($this->getTable()->hasDeferredFilters()) {
            $this->tableDeferredFilters = $this->tableFilters;
        }

        $this->handleTableFilterUpdates();
        $this->flushCachedTableRecords();
    }

    protected function migrateLegacySearchQuery(): void
    {
        if (! request()->filled('q')) {
            return;
        }

        $legacy = trim((string) request()->query('q', ''));

        if (filled($legacy) && blank($this->tableSearch)) {
            $this->tableSearch = $legacy;
        }

        $this->redirect($this->buildSubscribersListUrl(), navigate: true);
    }

    protected function buildSubscribersListUrl(): string
    {
        return $this->buildDirectoryToolbarUrl();
    }

    /**
     * @param  list<string>  $exclude
     */
    public function buildDirectoryToolbarUrl(array $exclude = []): string
    {
        return CustomerResource::getUrl('index', $this->buildDirectoryToolbarParameters($exclude));
    }

    /**
     * @param  list<string>  $exclude
     * @return array<string, mixed>
     */
    public function buildDirectoryToolbarParameters(array $exclude = []): array
    {
        $parameters = [];

        if (
            ! in_array('preset', $exclude, true)
            && property_exists($this, 'preset')
            && filled($this->preset ?? null)
            && ($this->preset ?? 'all') !== 'all'
        ) {
            $parameters['preset'] = $this->preset;
        }

        if (! in_array('search', $exclude, true) && filled($this->tableSearch)) {
            $parameters['tableSearch'] = trim((string) $this->tableSearch);
        }

        if (! in_array('zone', $exclude, true)) {
            $zoneId = data_get($this->tableFilters, 'zone_id.value');
            if (filled($zoneId)) {
                $parameters['tableFilters']['zone_id']['value'] = $zoneId;
            }
        }

        if (! in_array('status', $exclude, true)) {
            $status = data_get($this->tableFilters, 'status.value');
            if (filled($status)) {
                $parameters['tableFilters']['status']['value'] = $status;
            }
        }

        return $parameters;
    }

    public function getDirectoryFilterChipUrl(string $key): string
    {
        return match ($key) {
            'preset' => $this->buildDirectoryToolbarUrl(['preset']),
            'zone' => $this->buildDirectoryToolbarUrl(['zone']),
            'status' => $this->buildDirectoryToolbarUrl(['status']),
            'search' => $this->buildDirectoryToolbarUrl(['search']),
            default => CustomerResource::getUrl('index'),
        };
    }

    /** @var array<string, int>|null */
    private ?array $memoizedClientStats = null;

    /** @var array{total: int, active: int, inactive: int, due_clients: int, total_due: float}|null */
    private ?array $memoizedDirectoryStats = null;

    /** @var list<array{label: string, value: string, hint: string, tone: string, icon: string}> */
    public array $directoryStatCards = [];

    /** @var list<array{key: string, label: string, count: int}> */
    public array $directoryPresetTabs = [];

    public bool $directoryChromeReady = false;

    public function getDirectoryPageVariant(): ?string
    {
        return null;
    }

    public function loadDirectoryChrome(): void
    {
        if ($this->directoryChromeReady) {
            return;
        }

        $this->directoryStatCards = $this->getStatCards();

        if (method_exists($this, 'getPresetTabs')) {
            $this->directoryPresetTabs = $this->getPresetTabs();
        }

        $this->directoryChromeReady = true;
    }

    /**
     * @return array<int|string, string>
     */
    public function getDirectoryZoneFilterOptions(): array
    {
        $tenantId = TenantResolver::requiredTenantId();

        return Cache::remember(
            'clients_filter_zones:'.$tenantId,
            300,
            fn (): array => Zone::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryStatusFilterOptions(): array
    {
        return CustomerStatus::options();
    }

    public function setDirectorySearch(string $search = ''): void
    {
        $this->tableSearch = trim($search);
        $this->updatedTableSearch();
    }

    public function setDirectoryZoneFilter(mixed $zoneId = null): void
    {
        $this->ensureDirectoryTableFiltersInitialized();
        data_set($this->tableFilters, 'zone_id.value', filled($zoneId) ? (string) $zoneId : null);
        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->updatedTableFilters();
    }

    public function setDirectoryStatusFilter(mixed $status = null): void
    {
        $this->ensureDirectoryTableFiltersInitialized();
        data_set($this->tableFilters, 'status.value', filled($status) ? (string) $status : null);
        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->updatedTableFilters();
    }

    public function resetDirectoryToolbar(): void
    {
        $parameters = [];

        if (property_exists($this, 'preset') && filled($this->preset ?? null) && ($this->preset ?? 'all') !== 'all') {
            $parameters['preset'] = $this->preset;
        }

        $this->redirect(CustomerResource::getUrl('index', $parameters));
    }

    public function getDirectoryHeroTitle(): string
    {
        return $this->getPageTitle();
    }

    public function getDirectoryHeroSubtitle(): ?string
    {
        return $this->getSubheading();
    }

    public function getDirectoryResultSummary(): string
    {
        $count = $this->getAllTableRecordsCount();
        $label = $count === 1 ? 'client' : 'clients';

        // #region agent log
        $this->debugDirectoryLog('H1', 'UsesClientsDirectoryLayout.php:getDirectoryResultSummary', 'GET toolbar render', [
            'search' => $this->tableSearch,
            'zone' => data_get($this->tableFilters, 'zone_id.value'),
            'status' => data_get($this->tableFilters, 'status.value'),
            'count' => $count,
        ]);
        // #endregion

        return 'Showing '.number_format($count).' '.$label;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function getDirectoryFilterChips(): array
    {
        $chips = [];

        if (property_exists($this, 'preset') && ($this->preset ?? 'all') !== 'all') {
            $chips[] = [
                'key' => 'preset',
                'label' => 'List: '.ucfirst((string) $this->preset),
            ];
        }

        $zoneId = data_get($this->tableFilters, 'zone_id.value');
        if (filled($zoneId)) {
            $zoneName = $this->getDirectoryZoneFilterOptions()[$zoneId] ?? $zoneId;
            $chips[] = [
                'key' => 'zone',
                'label' => 'Zone: '.$zoneName,
            ];
        }

        $status = data_get($this->tableFilters, 'status.value');
        if (filled($status)) {
            $statusLabel = CustomerStatus::options()[$status] ?? $status;
            $chips[] = [
                'key' => 'status',
                'label' => 'Status: '.$statusLabel,
            ];
        }

        if (filled($this->tableSearch)) {
            $chips[] = [
                'key' => 'search',
                'label' => 'Search: “'.$this->tableSearch.'”',
            ];
        }

        return $chips;
    }

    /**
     * @return list<array{label: string, count: int|string, url: string, icon: string, tone: string}>
     */
    public function getDirectoryQuickLinks(): array
    {
        $stats = $this->getClientStats();
        $index = CustomerResource::getUrl('index');

        return [
            [
                'label' => 'Clients Center',
                'count' => 'Hub',
                'url' => ClientsHub::getUrl(),
                'icon' => 'heroicon-o-squares-2x2',
                'tone' => 'violet',
            ],
            [
                'label' => 'Live PPP',
                'count' => number_format((int) ($stats['online'] ?? 0)),
                'url' => OnlineClientsMonitoring::getUrl(),
                'icon' => 'heroicon-o-bolt',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Due clients',
                'count' => number_format((int) ($this->getDirectoryStats()['due_clients'] ?? 0)),
                'url' => CustomerResource::getUrl('due'),
                'icon' => 'heroicon-o-banknotes',
                'tone' => 'rose',
            ],
            [
                'label' => 'VIP clients',
                'count' => number_format(app(BillingAccountListCounts::class)->get('vip')),
                'url' => CustomerResource::getUrl('vip'),
                'icon' => 'heroicon-o-star',
                'tone' => 'amber',
            ],
            [
                'label' => 'Active',
                'count' => number_format((int) ($stats['active'] ?? 0)),
                'url' => CustomerResource::getUrl('active'),
                'icon' => 'heroicon-o-check-circle',
                'tone' => 'sky',
            ],
            [
                'label' => 'Expired',
                'count' => number_format((int) ($stats['expired'] ?? 0)),
                'url' => CustomerResource::getUrl('expired'),
                'icon' => 'heroicon-o-exclamation-circle',
                'tone' => 'slate',
            ],
        ];
    }

    protected function ensureDirectoryTableFiltersInitialized(): void
    {
        if ($this->tableFilters !== null) {
            return;
        }

        $this->getTableFiltersForm()->fill();
        $this->tableFilters = $this->getTableFiltersForm()->getState();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function debugDirectoryLog(string $hypothesisId, string $location, string $message, array $data = []): void
    {
        // #region agent log
        @file_put_contents(base_path('.cursor/debug-4550d5.log'), json_encode([
            'sessionId' => '4550d5',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ])."\n", FILE_APPEND);
        // #endregion
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function getPageTitle(): string
    {
        return static::getNavigationLabel() ?? 'Clients';
    }

    public function table(Table $table): Table
    {
        return CustomerResource::clientsDirectoryTable($table);
    }

    /**
     * @return array<string, int|float>
     */
    public function getClientStats(): array
    {
        if ($this->memoizedClientStats !== null) {
            return $this->memoizedClientStats;
        }

        return $this->memoizedClientStats = app(ClientsDashboardService::class)->listPresetSummary();
    }

    /**
     * @return array{total: int, active: int, inactive: int, due_clients: int, total_due: float}
     */
    public function getDirectoryStats(): array
    {
        if ($this->memoizedDirectoryStats !== null) {
            return $this->memoizedDirectoryStats;
        }

        $tenantId = TenantResolver::requiredTenantId();

        return $this->memoizedDirectoryStats = Cache::remember(
            'clients_directory_stats:'.$tenantId,
            120,
            function () use ($tenantId): array {
                $stats = $this->getClientStats();

                return [
                    'total' => (int) ($stats['total'] ?? 0),
                    'active' => (int) ($stats['active'] ?? 0),
                    'inactive' => max(0, (int) ($stats['total'] ?? 0) - (int) ($stats['active'] ?? 0)),
                    'due_clients' => app(StaffBillingKpiResolver::class)->dueClientsCount($tenantId),
                    'total_due' => CustomerBalanceDue::tenantOpenInvoiceDueSum($tenantId),
                ];
            },
        );
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string, icon: string, url?: string}>
     */
    public function getStatCards(): array
    {
        $stats = $this->getDirectoryStats();

        return [
            [
                'label' => 'Total clients',
                'value' => number_format($stats['total']),
                'hint' => 'All time clients',
                'tone' => 'violet',
                'icon' => 'heroicon-o-user-group',
                'url' => CustomerResource::getUrl('index'),
            ],
            [
                'label' => 'Active clients',
                'value' => number_format($stats['active']),
                'hint' => 'Currently active',
                'tone' => 'emerald',
                'icon' => 'heroicon-o-check-circle',
                'url' => CustomerResource::getUrl('active'),
            ],
            [
                'label' => 'Inactive clients',
                'value' => number_format($stats['inactive']),
                'hint' => 'Not active',
                'tone' => 'amber',
                'icon' => 'heroicon-o-user-minus',
                'url' => CustomerResource::getUrl('suspended'),
            ],
            [
                'label' => 'Due clients',
                'value' => number_format($stats['due_clients']),
                'hint' => 'Have pending dues',
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-circle',
                'url' => CustomerResource::getUrl('due'),
            ],
            [
                'label' => 'Total due',
                'value' => 'BDT '.number_format($stats['total_due'], 2),
                'hint' => 'From due clients',
                'tone' => 'sky',
                'icon' => 'heroicon-o-banknotes',
                'url' => CustomerResource::getUrl('due'),
            ],
        ];
    }
}
