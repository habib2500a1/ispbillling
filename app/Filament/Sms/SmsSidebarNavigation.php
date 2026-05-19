<?php

namespace App\Filament\Sms;

use App\Filament\Resources\NotificationLogResource;
use App\Support\SmsSidebarRegistry;

final class SmsSidebarNavigation
{
    public static function userCanSee(): bool
    {
        return NotificationLogResource::canViewAny();
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return SmsSidebarRegistry::navigationItems();
    }
}
