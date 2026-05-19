<?php

namespace App\Filament\Pages\Concerns;

/**
 * Hub pages are linked from curated sidebar registries only (not Filament auto-discovery).
 */
trait HidesHubNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
