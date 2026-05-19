<?php

namespace App\Filament\Billing;

use App\Filament\Resources\InvoiceResource;
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

            $panel->navigationItems(InvoiceResource::getBillingNavigationItems());
        });
    }

    public static function userCanSee(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
            'admin',
            'billing-manager',
            'cashier',
            'accounts-manager',
            'branch-manager',
        ])) {
            return true;
        }

        return $user->can('billing.view')
            || $user->can('payments.view')
            || InvoiceResource::canViewAny();
    }
}
