<?php

namespace App\Filament\Concerns;

use App\Support\Rbac\StaffCapability;

trait ChecksDashboardWidgetAccess
{
    public static function canView(): bool
    {
        return StaffCapability::for(auth()->user())->canSeeWidget(static::class);
    }
}
