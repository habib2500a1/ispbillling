<?php

namespace App\Filament\Pages\Concerns;

use App\Support\Rbac\StaffCapability;

trait InventoryReportPage
{
    use HidesHubNavigation;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return StaffCapability::for($user)->canInventory();
    }
}
