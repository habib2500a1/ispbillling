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
        Event::listen(ServingFilament::class, function (): void {
            if (! auth()->check()) {
                return;
            }

            $panel = Filament::getCurrentPanel();
            if ($panel === null || $panel->getId() !== 'admin') {
                return;
            }

            $items = InventorySidebarRegistry::navigationItems();
            if ($items !== []) {
                $panel->navigationItems($items);
            }
        });
    }
}
