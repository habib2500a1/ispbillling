<?php

namespace App\Filament\Pages\Concerns;

/**
 * Hub pages register in the sidebar when the user can open them (search + mobile dock + PC sidebar).
 */
trait HidesHubNavigation
{
    public static function shouldRegisterNavigation(): bool
    {
        if (method_exists(static::class, 'canAccess')) {
            return (bool) static::canAccess();
        }

        return auth()->check();
    }
}
