<?php

namespace App\Filament\Support;

use App\Support\SupportSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class SupportSidebarNavigation
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

            $items = SupportSidebarRegistry::navigationItems();
            if ($items !== []) {
                $panel->navigationItems($items);
            }
        });
    }
}
