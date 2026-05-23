<?php

namespace App\Filament\Inventory;

use App\Support\InventorySidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class InventorySidebarNavigation
{
    public static function register(): void
    {
        // Registered via IspSidebarNavigation::allNavigationItems() (Inventory Pro + OLT & Tools groups).
    }
}
