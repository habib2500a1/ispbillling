<?php

namespace App\Filament\Billing;

use App\Support\BillingSidebarRegistry;
use App\Support\Rbac\StaffCapability;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class BillingSidebarNavigation
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

            $panel->navigationItems(BillingSidebarRegistry::navigationItems());
        });
    }

    public static function userCanSee(): bool
    {
        $capability = StaffCapability::for(auth()->user());

        return $capability->canBilling() || $capability->canPayments();
    }
}
