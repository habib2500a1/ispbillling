<?php

namespace App\Filament\Pages\Concerns;

/**
 * Hub / directory pages stay reachable via ⌘K search and dashboard links — not sidebar clutter.
 */
trait HidesHubNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
