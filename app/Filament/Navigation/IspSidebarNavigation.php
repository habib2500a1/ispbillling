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
 * Single sidebar builder — module links on every admin page.
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
        $merged = [];

        static::appendIf($merged, ClientsSidebarNavigation::userCanSee(), ClientsSidebarRegistry::navigationItems());
        static::appendIf($merged, BillingSidebarNavigation::userCanSee(), BillingSidebarRegistry::navigationItems());
        static::appendInventoryItems($merged);
        static::appendIf($merged, PaymentsSidebarRegistry::hasVisibleEntries(), PaymentsSidebarRegistry::navigationItems());
        static::appendIf($merged, NetworkSidebarNavigation::userCanSee(), NetworkSidebarRegistry::navigationItems());
        static::appendIf($merged, OltSidebarNavigation::userCanSee(), OltSidebarRegistry::navigationItems());
        static::appendIf($merged, SmsSidebarNavigation::userCanSee(), SmsSidebarRegistry::navigationItems());
        static::appendIf($merged, SupportSidebarRegistry::hasVisibleEntries(), SupportSidebarRegistry::navigationItems());
        static::appendIf($merged, ReportsSidebarNavigation::userCanSee(), ReportsSidebarRegistry::navigationItems());
        static::appendIf($merged, AccountsSidebarNavigation::userCanSee(), AccountsSidebarRegistry::navigationItems());
        static::appendIf($merged, ResellerSidebarNavigation::userCanSee(), ResellerSidebarRegistry::navigationItems());
        static::appendIf($merged, HrmSidebarNavigation::userCanSee(), HrmSidebarRegistry::navigationItems());
        static::appendIf($merged, BwSidebarNavigation::userCanSee(), BwSidebarRegistry::navigationItems());
        static::appendIf($merged, SettingsSidebarNavigation::userCanSee(), SettingsSidebarRegistry::navigationItems());
        static::appendIf($merged, SystemSidebarRegistry::hasVisibleEntries(), SystemSidebarRegistry::navigationItems());

        return static::dedupeNavigationItems($merged);
    }

    /**
     * Prevent duplicate sidebar links (same URL from Filament discovery + registry).
     *
     * @param  array<\Filament\Navigation\NavigationItem>  $items
     * @return array<\Filament\Navigation\NavigationItem>
     */
    private static function dedupeNavigationItems(array $items): array
    {
        /** @var array<string, array{item: \Filament\Navigation\NavigationItem, priority: int}> $winners */
        $winners = [];

        foreach ($items as $item) {
            $url = (string) $item->getUrl();
            if ($url === '') {
                continue;
            }

            $priority = self::navigationGroupPriority((string) ($item->getGroup() ?? ''));

            if (! isset($winners[$url]) || $priority > $winners[$url]['priority']) {
                $winners[$url] = ['item' => $item, 'priority' => $priority];
            }
        }

        $emitted = [];
        $out = [];

        foreach ($items as $item) {
            $url = (string) $item->getUrl();

            if ($url === '') {
                $out[] = $item;

                continue;
            }

            if (isset($emitted[$url])) {
                continue;
            }

            $out[] = $winners[$url]['item'];
            $emitted[$url] = true;
        }

        return $out;
    }

    private static function navigationGroupPriority(string $group): int
    {
        return match ($group) {
            'OLT & Tools' => 100,
            'Network' => 90,
            'Inventory Pro' => 20,
            default => 50,
        };
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

    /**
     * @param  array<\Filament\Navigation\NavigationItem>  $target
     */
    private static function appendInventoryItems(array &$target): void
    {
        if (! InventorySidebarRegistry::hasVisibleEntries()) {
            return;
        }

        try {
            $items = InventorySidebarRegistry::navigationItems();
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        if ($items !== []) {
            array_push($target, ...$items);
        }
    }
}
