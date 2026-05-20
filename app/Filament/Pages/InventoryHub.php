<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class InventoryHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static string $view = 'filament.pages.inventory-hub';

    protected static ?string $navigationLabel = 'Inventory & purchase';

    protected static ?string $title = 'Inventory & purchase';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canInventory();
    }
}
