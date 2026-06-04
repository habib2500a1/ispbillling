<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\ProductResource;
use App\Services\Inventory\InventoryDashboardService;
use App\Support\TenantResolver;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.list-products';

    /** @var array<string, mixed> */
    public array $inventorySummary = [];

    public function mount(): void
    {
        parent::mount();

        $tenantId = TenantResolver::requiredTenantId();

        $this->inventorySummary = Cache::remember(
            'products_list_summary:'.$tenantId,
            120,
            fn (): array => app(InventoryDashboardService::class)->summary($tenantId),
        );
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('inventory_hub')
                ->label('Inventory center')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(InventoryHub::getUrl()),
            Actions\CreateAction::make()
                ->label('New product')
                ->icon('heroicon-m-plus'),
        ];
    }
}
