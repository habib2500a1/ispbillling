<?php

namespace App\Filament\IspOs;

use App\Support\IspOsSidebarRegistry;

final class IspOsSidebarNavigation
{
    public static function userCanSee(): bool
    {
        return IspOsSidebarRegistry::hasVisibleEntries();
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return IspOsSidebarRegistry::navigationItems();
    }
}
