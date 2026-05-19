<?php

namespace App\Filament\Accounts;

use App\Support\AccountsSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class AccountsSidebarNavigation
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
        foreach (AccountsSidebarRegistry::definitions() as $entry) {
            if (AccountsSidebarRegistry::canSeeEntry($entry['key'])) {
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
        return AccountsSidebarRegistry::navigationItems();
    }
}
