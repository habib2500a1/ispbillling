<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Package;
use App\Services\Clients\ClientsDashboardService;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.list-customers';

    #[Url(as: 'preset')]
    public string $preset = 'all';

    public function mount(): void
    {
        parent::mount();

        $tenantId = TenantResolver::currentTenantId();
        if ($tenantId !== null) {
            app(\App\Services\Bandwidth\BandwidthCollectionService::class)
                ->refreshOnlineFlagsForTenant($tenantId);
        }
    }

    public function getHeading(): string
    {
        return 'All clients';
    }

    public function getSubheading(): ?string
    {
        return 'Search by name, code, phone, or PPPoE ID. Use tabs and filters — bulk actions are in the table toolbar.';
    }

    /**
     * @return array<string, int>
     */
    public function getClientStats(): array
    {
        return app(ClientsDashboardService::class)->summary();
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

        return match ($this->preset) {
            'online' => $query
                ->where('is_ppp_online', true)
                ->where('status', '!=', CustomerStatus::TERMINATED),
            'offline' => $query
                ->where('is_ppp_online', false)
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
            Actions\Action::make('export')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(\App\Filament\Pages\ExportClientsReport::getUrl()),
            Actions\CreateAction::make()
                ->label('Add client')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}
