<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\Concerns\UsesClientsDirectoryLayout;
use App\Models\Package;
use App\Services\Import\IspDigitalCurrentBillingSyncService;
use App\Services\Import\IspDigitalPriceSyncService;
use App\Services\Import\IspDigitalSessionClient;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Throwable;

class ListCustomers extends ListRecords
{
    use UsesClientsDirectoryLayout;

    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.list-customers';

    #[Url(as: 'preset')]
    public string $preset = 'all';

    /**
     * @return array<string, mixed>
     */
    protected function queryString(): array
    {
        return [
            'tableSearch' => ['except' => ''],
            'tableFilters' => ['except' => null],
        ];
    }

    public function mount(): void
    {
        parent::mount();
        $this->loadDirectoryChrome();
        $this->migrateLegacySearchQuery();
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return 'Search, filter, and manage all subscribers — PPPoE, packages, billing, and bulk actions.';
    }

    public function getPageTitle(): string
    {
        return 'All clients';
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

    protected function getTableQuery(): ?Builder
    {
        $query = CustomerResource::clientsDirectoryEloquentQuery();

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
