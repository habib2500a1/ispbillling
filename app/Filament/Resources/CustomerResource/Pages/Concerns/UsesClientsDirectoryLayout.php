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

        $this->memoizedDirectoryTableCount = null;
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function updatedTableFilters(): void
    {
        if ($this->getTable()->hasDeferredFilters()) {
            $this->tableDeferredFilters = $this->tableFilters;
        }

        $this->memoizedDirectoryTableCount = null;
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

        foreach (['package_id', 'area_id', 'reseller_id', 'network_access_state', 'remaining_days', 'onu_ownership'] as $filterKey) {
            if (in_array($filterKey, $exclude, true)) {
                continue;
            }

            $value = data_get($this->tableFilters, "{$filterKey}.value");
            if (filled($value)) {
                $parameters['tableFilters'][$filterKey]['value'] = $value;
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
            'package' => $this->buildDirectoryToolbarUrl(['package_id']),
            'area' => $this->buildDirectoryToolbarUrl(['area_id']),
            'owner' => $this->buildDirectoryToolbarUrl(['reseller_id']),
            'line' => $this->buildDirectoryToolbarUrl(['network_access_state']),
            'remaining' => $this->buildDirectoryToolbarUrl(['remaining_days']),
            'onu' => $this->buildDirectoryToolbarUrl(['onu_ownership']),
            'search' => $this->buildDirectoryToolbarUrl(['search']),
            default => CustomerResource::getUrl('index'),
        };
    }

    /** @var array<string, int>|null */
    private ?array $memoizedClientStats = null;

    /** @var array{total: int, active: int, inactive: int, due_clients: int, total_due: float}|null */
    private ?array $memoizedDirectoryStats = null;

    private ?int $memoizedDirectoryTableCount = null;

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

    public function setDirectoryPackageFilter(mixed $packageId = null): void
    {
        $this->setDirectorySelectFilter('package_id', $packageId);
    }

    public function setDirectoryAreaFilter(mixed $areaId = null): void
    {
        $this->setDirectorySelectFilter('area_id', $areaId);
    }

    public function setDirectoryResellerFilter(mixed $resellerId = null): void
    {
        $this->setDirectorySelectFilter('reseller_id', $resellerId);
    }

    public function setDirectoryLineFilter(mixed $line = null): void
    {
        $this->setDirectorySelectFilter('network_access_state', $line);
    }

    public function setDirectoryRemainingDaysFilter(mixed $value = null): void
    {
        $this->setDirectorySelectFilter('remaining_days', $value);
    }

    public function setDirectoryOnuOwnershipFilter(mixed $value = null): void
    {
        $this->setDirectorySelectFilter('onu_ownership', $value);
    }

    protected function setDirectorySelectFilter(string $key, mixed $value): void
    {
        $this->ensureDirectoryTableFiltersInitialized();
        data_set($this->tableFilters, "{$key}.value", filled($value) ? (string) $value : null);
        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->updatedTableFilters();
    }

    /**
     * @return array<int|string, string>
     */
    public function getDirectoryPackageFilterOptions(): array
    {
        return CustomerResource::directoryFilterPackages();
    }

    /**
     * @return array<int|string, string>
     */
    public function getDirectoryAreaFilterOptions(): array
    {
        return CustomerResource::directoryFilterAreas();
    }

    /**
     * @return array<int|string, string>
     */
    public function getDirectoryResellerFilterOptions(): array
    {
        return CustomerResource::directoryFilterResellers();
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryLineFilterOptions(): array
    {
        return [
            'active' => 'Line on',
            'suspended' => 'Line off',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryRemainingDaysFilterOptions(): array
    {
        return [
            'expired' => 'Expired',
            '0_3' => '0–3 days',
            '4_7' => '4–7 days',
            '8_30' => '8–30 days',
            '30_plus' => '30+ days',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getDirectoryOnuOwnershipFilterOptions(): array
    {
        return \App\Support\OnuOwnership::options();
    }

    public function getDirectoryActiveFilterCount(): int
    {
        return count($this->getDirectoryFilterChips());
    }

    public function resetDirectoryToolbar(): void
    {
        $this->tableSearch = '';
        $this->ensureDirectoryTableFiltersInitialized();

        foreach (['zone_id', 'status', 'package_id', 'area_id', 'reseller_id', 'network_access_state', 'remaining_days', 'onu_ownership'] as $key) {
            data_set($this->tableFilters, "{$key}.value", null);
        }

        $this->getTableFiltersForm()->fill($this->tableFilters);
        $this->updatedTableFilters();
        $this->updatedTableSearch();
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
        $count = $this->getCachedDirectoryTableRecordsCount();
        $label = $count === 1 ? 'client' : 'clients';

        return 'Showing '.number_format($count).' '.$label;
    }

    protected function getCachedDirectoryTableRecordsCount(): int
    {
        if ($this->memoizedDirectoryTableCount !== null) {
            return $this->memoizedDirectoryTableCount;
        }

        $tenantId = \App\Support\TenantResolver::requiredTenantId();
        $cacheKey = 'clients_dir_count:'.$tenantId.':'.md5(json_encode([
            'class' => static::class,
            'preset' => property_exists($this, 'preset') ? ($this->preset ?? 'all') : 'all',
            'search' => trim((string) ($this->tableSearch ?? '')),
            'filters' => $this->tableFilters ?? [],
        ]));

        return $this->memoizedDirectoryTableCount = (int) \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            45,
            fn (): int => (int) $this->getAllTableRecordsCount(),
        );
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

        $packageId = data_get($this->tableFilters, 'package_id.value');
        if (filled($packageId)) {
            $chips[] = [
                'key' => 'package',
                'label' => 'Package: '.($this->getDirectoryPackageFilterOptions()[$packageId] ?? $packageId),
            ];
        }

        $areaId = data_get($this->tableFilters, 'area_id.value');
        if (filled($areaId)) {
            $chips[] = [
                'key' => 'area',
                'label' => 'Area: '.($this->getDirectoryAreaFilterOptions()[$areaId] ?? $areaId),
            ];
        }

        $resellerId = data_get($this->tableFilters, 'reseller_id.value');
        if (filled($resellerId)) {
            $chips[] = [
                'key' => 'owner',
                'label' => 'Owner: '.($this->getDirectoryResellerFilterOptions()[$resellerId] ?? $resellerId),
            ];
        }

        $line = data_get($this->tableFilters, 'network_access_state.value');
        if (filled($line)) {
            $chips[] = [
                'key' => 'line',
                'label' => 'Line: '.($this->getDirectoryLineFilterOptions()[$line] ?? $line),
            ];
        }

        $remaining = data_get($this->tableFilters, 'remaining_days.value');
        if (filled($remaining)) {
            $chips[] = [
                'key' => 'remaining',
                'label' => 'Expiry: '.($this->getDirectoryRemainingDaysFilterOptions()[$remaining] ?? $remaining),
            ];
        }

        $onuOwnership = data_get($this->tableFilters, 'onu_ownership.value');
        if (filled($onuOwnership)) {
            $chips[] = [
                'key' => 'onu',
                'label' => 'ONU: '.($this->getDirectoryOnuOwnershipFilterOptions()[$onuOwnership] ?? $onuOwnership),
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
