<?php

namespace App\Filament\Navigation;

use App\Filament\Accounts\AccountsSidebarNavigation;
use App\Filament\Billing\BillingSidebarNavigation;
use App\Filament\Bw\BwSidebarNavigation;
use App\Filament\Clients\ClientsSidebarNavigation;
use App\Filament\Hrm\HrmSidebarNavigation;
use App\Filament\Network\NetworkSidebarNavigation;
use App\Filament\Olt\OltSidebarNavigation;
use App\Filament\Reports\ReportsSidebarNavigation;
use App\Filament\Resellers\ResellerSidebarNavigation;
use App\Filament\Settings\SettingsSidebarNavigation;
use App\Filament\Sms\SmsSidebarNavigation;
use App\Support\AccountsSidebarRegistry;
use App\Support\BillingSidebarRegistry;
use App\Support\BwSidebarRegistry;
use App\Support\ClientsSidebarRegistry;
use App\Support\HrmSidebarRegistry;
use App\Support\InventorySidebarRegistry;
use App\Support\MainSidebarRegistry;
use App\Support\NetworkSidebarRegistry;
use App\Support\OltSidebarRegistry;
use App\Support\PaymentsSidebarRegistry;
use App\Support\ReportsSidebarRegistry;
use App\Support\ResellerSidebarRegistry;
use App\Support\SettingsSidebarRegistry;
use App\Support\SmsSidebarRegistry;
use App\Support\SupportSidebarRegistry;
use App\Support\SystemSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;

/**
 * Single sidebar builder — "Main" menu + module links on every admin page.
 */
final class IspSidebarNavigation
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

            $items = static::allNavigationItems();

            if ($items !== []) {
                $panel->navigationItems($items);
            }
        });
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function allNavigationItems(): array
    {
        $merged = MainSidebarRegistry::navigationItems();

        static::appendIf($merged, ClientsSidebarNavigation::userCanSee(), ClientsSidebarRegistry::navigationItems());
        static::appendIf($merged, BillingSidebarNavigation::userCanSee(), BillingSidebarRegistry::navigationItems());
        static::appendIf($merged, true, PaymentsSidebarRegistry::navigationItems());
        static::appendIf($merged, NetworkSidebarNavigation::userCanSee(), NetworkSidebarRegistry::navigationItems());
        static::appendIf($merged, OltSidebarNavigation::userCanSee(), OltSidebarRegistry::navigationItems());
        static::appendIf($merged, SmsSidebarNavigation::userCanSee(), SmsSidebarRegistry::navigationItems());
        static::appendIf($merged, true, SupportSidebarRegistry::navigationItems());
        static::appendIf($merged, ReportsSidebarNavigation::userCanSee(), ReportsSidebarRegistry::navigationItems());
        static::appendIf($merged, AccountsSidebarNavigation::userCanSee(), AccountsSidebarRegistry::navigationItems());
        static::appendIf($merged, ResellerSidebarNavigation::userCanSee(), ResellerSidebarRegistry::navigationItems());
        static::appendIf($merged, HrmSidebarNavigation::userCanSee(), HrmSidebarRegistry::navigationItems());
        static::appendIf($merged, BwSidebarNavigation::userCanSee(), BwSidebarRegistry::navigationItems());
        static::appendIf($merged, true, InventorySidebarRegistry::navigationItems());
        static::appendIf($merged, SettingsSidebarNavigation::userCanSee(), SettingsSidebarRegistry::navigationItems());
        static::appendIf($merged, true, SystemSidebarRegistry::navigationItems());

        return $merged;
    }

    /**
     * @param  array<\Filament\Navigation\NavigationItem>  $target
     * @param  array<\Filament\Navigation\NavigationItem>  $items
     */
    private static function appendIf(array &$target, bool $condition, array $items): void
    {
        if ($condition && $items !== []) {
            array_push($target, ...$items);
        }
    }
}
