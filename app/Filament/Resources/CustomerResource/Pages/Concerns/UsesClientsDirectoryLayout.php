<?php

namespace App\Filament\Resources\CustomerResource\Pages\Concerns;

use App\Filament\Resources\CustomerResource;
use App\Services\Clients\ClientsDashboardService;
use App\Services\Mobile\StaffBillingKpiResolver;
use App\Support\CustomerBalanceDue;
use App\Support\TenantResolver;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

/**
 * Shared Sheba-Fi–style client directory (filters, columns, bulk bar).
 */
trait UsesClientsDirectoryLayout
{
    use AppliesClientsDirectoryTableQuery;

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
     * @return list<array{label: string, value: string, hint: string, tone: string, icon: string}>
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
            ],
            [
                'label' => 'Active clients',
                'value' => number_format($stats['active']),
                'hint' => 'Currently active',
                'tone' => 'emerald',
                'icon' => 'heroicon-o-check-circle',
            ],
            [
                'label' => 'Inactive clients',
                'value' => number_format($stats['inactive']),
                'hint' => 'Not active',
                'tone' => 'amber',
                'icon' => 'heroicon-o-user-minus',
            ],
            [
                'label' => 'Due clients',
                'value' => number_format($stats['due_clients']),
                'hint' => 'Have pending dues',
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-circle',
            ],
            [
                'label' => 'Total due',
                'value' => 'BDT '.number_format($stats['total_due'], 2),
                'hint' => 'From due clients',
                'tone' => 'sky',
                'icon' => 'heroicon-o-banknotes',
            ],
        ];
    }
}
