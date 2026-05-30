<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Package;
use App\Services\Clients\ClientsDashboardService;
use App\Services\Import\IspDigitalCurrentBillingSyncService;
use App\Services\Mobile\StaffBillingKpiResolver;
use App\Services\Import\IspDigitalPriceSyncService;
use App\Services\Import\IspDigitalSessionClient;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Throwable;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.list-customers';

    #[Url(as: 'preset')]
    public string $preset = 'all';

    public function mount(): void
    {
        parent::mount();
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * @return array<string, int|float>
     */
    public function getClientStats(): array
    {
        return app(ClientsDashboardService::class)->summary();
    }

    /**
     * @return array{total: int, active: int, inactive: int, due_clients: int, total_due: float}
     */
    public function getDirectoryStats(): array
    {
        $stats = $this->getClientStats();
        $tenantId = TenantResolver::requiredTenantId();

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'active' => (int) ($stats['active'] ?? 0),
            'inactive' => max(0, (int) ($stats['total'] ?? 0) - (int) ($stats['active'] ?? 0)),
            'due_clients' => app(StaffBillingKpiResolver::class)->dueClientsCount($tenantId),
            'total_due' => CustomerBalanceDue::tenantOpenInvoiceDueSum($tenantId),
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    public function getPresetTabs(): array
    {
        $stats = $this->getClientStats();

        return [
            ['key' => 'all', 'label' => 'All', 'count' => $stats['total'] ?? 0],
            ['key' => 'online', 'label' => 'Online', 'count' => $stats['online'] ?? 0],
            ['key' => 'offline', 'label' => 'Offline', 'count' => $stats['offline'] ?? 0],
            ['key' => 'home', 'label' => 'Home', 'count' => $stats['home'] ?? 0],
            ['key' => 'reseller', 'label' => 'Reseller', 'count' => $stats['reseller'] ?? 0],
        ];
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

    public function table(Table $table): Table
    {
        return CustomerResource::clientsDirectoryTable($table);
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($query === null) {
            return null;
        }

        $tenantId = \App\Support\TenantResolver::requiredTenantId();
        $bandwidth = app(\App\Services\Bandwidth\BandwidthCollectionService::class);

        return match ($this->preset) {
            'online' => $bandwidth
                ->applyDisplayedOnlineFilter($query, $tenantId, true)
                ->where('status', '!=', CustomerStatus::TERMINATED),
            'offline' => $bandwidth
                ->applyDisplayedOnlineFilter($query, $tenantId, false)
                ->where('status', '!=', CustomerStatus::TERMINATED),
            'home' => $query
                ->where('status', '!=', CustomerStatus::TERMINATED)
                ->whereNotNull('package_id')
                ->whereIn('package_id', $this->homePackageIds()),
            'reseller' => $query
                ->where('status', '!=', CustomerStatus::TERMINATED)
                ->whereNotNull('reseller_id'),
            default => $query,
        };
    }

    /**
     * @return list<int>
     */
    private function homePackageIds(): array
    {
        $tenantId = TenantResolver::currentTenantId() ?? 0;

        return Cache::remember(
            'clients_home_package_ids:'.$tenantId,
            300,
            fn (): array => Package::query()
                ->where('tenant_id', $tenantId)
                ->where('type', '!=', 'hotspot')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncIspDigitalPackages')
                ->label('Sync packages & bills')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Sync from ISP Digital')
                ->modalDescription('Updates each client’s package, monthly bill, package prices, and current balance/due from pay.anetbd.com. Safe to run again.')
                ->action(function (): void {
                    $password = (string) config('isp_digital.password');
                    if ($password === '') {
                        Notification::make()
                            ->title('ISP Digital password missing')
                            ->body('Set ISP_DIGITAL_PASSWORD in .env')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $client = new IspDigitalSessionClient(
                            (string) config('isp_digital.base_url'),
                            (string) config('isp_digital.username'),
                            $password,
                        );

                        $prices = app(IspDigitalPriceSyncService::class)->syncAll($client);
                        $billing = app(IspDigitalCurrentBillingSyncService::class)->syncAll($client);

                        Notification::make()
                            ->title('ISP Digital sync complete')
                            ->body(sprintf(
                                'Bills: %d users · Package prices: %d · Billing rows: %d',
                                $prices['customers_updated'],
                                $prices['packages_updated'],
                                $billing['customers'],
                            ))
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
