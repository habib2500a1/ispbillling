<?php

namespace App\Filament\Navigation;

use Filament\Navigation\NavigationManager;

/**
 * Filters final sidebar groups so OLT links never stay under Inventory Pro.
 */
final class IspNavigationManager extends NavigationManager
{
    /**
     * @return array<\Filament\Navigation\NavigationGroup>
     */
    public function get(): array
    {
        return IspSidebarNavigation::postProcessNavigationGroups(parent::get());
    }
}
