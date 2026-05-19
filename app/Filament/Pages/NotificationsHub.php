<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use Filament\Pages\Page;

class NotificationsHub extends Page
{
    use HidesHubNavigation;
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.notifications-hub';

    protected static ?string $navigationLabel = 'Notifications';

    protected static ?string $title = 'SMS & notifications';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]) ?? false;
    }

}
