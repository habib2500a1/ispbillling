<?php

namespace App\Filament\Bw;

use App\Filament\Resources\BandwidthClientResource;
use App\Support\BwSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class BwSidebarNavigation
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

            if (! static::userCanSee()) {
                return;
            }

            $panel->navigationItems(static::navigationItems());
        });
    }

    public static function userCanSee(): bool
    {
        return BandwidthClientResource::canViewAny();
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return BwSidebarRegistry::navigationItems();
    }
}
