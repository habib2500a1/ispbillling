<?php

namespace App\Filament\Pages;

use App\Filament\Resources\InventorySaleResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\StockMovementResource;
use App\Services\Inventory\InventoryDashboardService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class InventoryHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static string $view = 'filament.pages.inventory-hub';

    protected static ?string $navigationLabel = 'Inventory center';

    protected static ?string $title = 'Inventory Pro';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $summary = [];

    public function mount(): void
    {
        $this->summary = app(InventoryDashboardService::class)->summary();
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canInventory();
    }

    public function getShopUrl(): string
    {
        return route('shop.index');
    }
}
