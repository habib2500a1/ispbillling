<?php

namespace App\Filament\Pages;

use App\Filament\Settings\SettingsSidebarNavigation;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class SettingsHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static string $view = 'filament.pages.settings-hub';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings-hub';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ManageCompanySetup::canAccess();
    }

    public static function registerNavigationItems(): void
    {
        // Curated sidebar: SettingsSidebarNavigation → SettingsSidebarRegistry.
    }
}
