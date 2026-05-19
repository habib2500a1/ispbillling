<?php

namespace App\Filament\Reports;

use App\Filament\Pages\PaymentsReport;
use App\Support\ReportsSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class ReportsSidebarNavigation
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
        foreach (ReportsSidebarRegistry::definitions() as $entry) {
            if (ReportsSidebarRegistry::canSeeEntry($entry['key'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function navigationItems(): array
    {
        return ReportsSidebarRegistry::navigationItems();
    }
}
