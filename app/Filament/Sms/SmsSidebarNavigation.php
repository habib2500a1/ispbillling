<?php

namespace App\Filament\Sms;

use App\Support\SmsSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class SmsSidebarNavigation
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
        return \App\Filament\Resources\NotificationLogResource::canViewAny();
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return SmsSidebarRegistry::navigationItems();
    }
}
