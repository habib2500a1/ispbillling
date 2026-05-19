<?php

namespace App\Filament\Payments;

use App\Support\PaymentsSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

final class PaymentsSidebarNavigation
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

            $items = PaymentsSidebarRegistry::navigationItems();
            if ($items !== []) {
                $panel->navigationItems($items);
            }
        });
    }
}
