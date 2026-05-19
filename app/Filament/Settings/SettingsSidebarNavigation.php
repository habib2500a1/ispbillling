<?php

namespace App\Filament\Settings;

use App\Filament\Pages\ManageCompanySetup;
use App\Support\SettingsSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class SettingsSidebarNavigation
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
        return ManageCompanySetup::canAccess();
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return SettingsSidebarRegistry::navigationItems();
    }
}
