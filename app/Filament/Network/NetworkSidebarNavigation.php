<?php

namespace App\Filament\Network;

use App\Support\NetworkSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class NetworkSidebarNavigation
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
        foreach (NetworkSidebarRegistry::definitions() as $entry) {
            if (NetworkSidebarRegistry::canSeeEntry($entry['key'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return NetworkSidebarRegistry::navigationItems();
    }
}
