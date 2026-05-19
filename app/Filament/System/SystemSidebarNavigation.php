<?php

namespace App\Filament\System;

use App\Support\SystemSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class SystemSidebarNavigation
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

            $items = SystemSidebarRegistry::navigationItems();
            if ($items !== []) {
                $panel->navigationItems($items);
            }
        });
    }
}
