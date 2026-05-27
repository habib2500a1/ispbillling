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
use App\Support\SuperadminQuickSidebarRegistry;
use App\Support\SystemSidebarRegistry;
use Filament\Events\ServingFilament;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use ReflectionClass;

/**
 * Single sidebar builder — module links on every admin page.
 */
final class IspSidebarNavigation
{
    public static function register(): void
    {
        Event::listen(ServingFilament::class, function (): void {
            if (! Auth::check()) {
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

        // Runs after any legacy listeners that still register Inventory Pro links.
        Filament::serving(function (): void {
            if (! Auth::check()) {
                return;
            }

            $panel = Filament::getCurrentPanel();
            if ($panel === null || $panel->getId() !== 'admin') {
                return;
            }

            static::stripOltLinksFromInventoryProGroup($panel);
            static::normalizeOltGroupOnPanel($panel);
            static::ensureOltNavigationOnPanel($panel);
        });
    }

    /**
     * OLT pages live under «OLT & Tools» — remove stray OLT URLs from Inventory Pro only.
     */
    public static function stripOltLinksFromInventoryProGroup(Panel $panel): void
    {
        $items = static::panelNavigationItems($panel);

        $filtered = array_values(array_filter(
            $items,
            static fn (NavigationItem $item): bool => ! static::isOltLinkMisplacedInInventoryGroup($item),
        ));

        static::setPanelNavigationItems($panel, $filtered);
    }

    public static function isOltLinkMisplacedInInventoryGroup(NavigationItem $item, ?string $parentGroupLabel = null): bool
    {
        $group = $parentGroupLabel ?? (string) ($item->getGroup() ?? '');

        if (! in_array($group, [InventorySidebarRegistry::GROUP_LABEL, 'Inventory'], true)) {
            return false;
        }

        $url = (string) $item->getUrl();

        if ($url !== '' && in_array($url, static::oltSidebarUrls(), true)) {
            return true;
        }

        $label = strtolower(trim((string) $item->getLabel()));

        if (in_array($label, [
            'olts',
            'olt',
            'olt list',
            'olt center',
            'optical database',
            'topology',
            'mac table',
            'laser thresholds',
            'olt mac table',
        ], true)) {
            return true;
        }

        return $url !== '' && static::isOltAdminPath($url);
    }

    public static function isOltAdminPath(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return str_contains($path, '/olts')
            || str_contains($path, 'olt-hub')
            || str_contains($path, 'optical-noc')
            || str_contains($path, 'olt-mac-table')
            || str_contains($path, 'optical-laser-settings')
            || str_contains($path, 'network-topology');
    }

    /**
     * @param  array<NavigationGroup>  $groups
     * @return array<NavigationGroup>
     */
    public static function postProcessNavigationGroups(array $groups): array
    {
        $processed = [];

        foreach ($groups as $group) {
            $label = (string) ($group->getLabel() ?? '');

            if (in_array($label, [InventorySidebarRegistry::GROUP_LABEL, 'Inventory'], true)) {
                $items = collect($group->getItems())
                    ->filter(fn (NavigationItem $item): bool => ! static::isOltLinkMisplacedInInventoryGroup($item, $label))
                    ->all();

                if ($items === []) {
                    continue;
                }

                $group->items($items);
            }

            $processed[] = $group;
        }

        return static::ensureOltToolsNavigationGroup($processed);
    }

    /**
     * @param  array<NavigationGroup>  $groups
     * @return array<NavigationGroup>
     */
    public static function ensureOltToolsNavigationGroup(array $groups): array
    {
        if (! OltSidebarNavigation::userCanSee()) {
            return $groups;
        }

        $oltLabel = OltSidebarRegistry::GROUP_LABEL;
        $hasOltGroup = false;

        foreach ($groups as $group) {
            if ((string) ($group->getLabel() ?? '') === $oltLabel) {
                $hasOltGroup = true;

                break;
            }
        }

        if ($hasOltGroup) {
            return $groups;
        }

        $items = OltSidebarRegistry::navigationItems();

        if ($items === []) {
            return $groups;
        }

        $groups[] = NavigationGroup::make($oltLabel)
            ->items($items)
            ->collapsible(false);

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function oltSidebarUrls(): array
    {
        static $urls = null;

        if ($urls !== null) {
            return $urls;
        }

        $urls = [];

        foreach (OltSidebarRegistry::definitions() as $entry) {
            $urls[] = $entry['url'];
        }

        return $urls = array_values(array_unique($urls));
    }

    public static function normalizeOltGroupOnPanel(Panel $panel): void
    {
        $items = static::panelNavigationItems($panel);

        foreach ($items as $item) {
            if ((string) ($item->getGroup() ?? '') === 'OLT') {
                $item->group(OltSidebarRegistry::GROUP_LABEL);
            }
        }

        static::setPanelNavigationItems($panel, $items);
    }

    public static function ensureOltNavigationOnPanel(Panel $panel): void
    {
        if (! OltSidebarNavigation::userCanSee()) {
            return;
        }

        $existingUrls = [];
        foreach (static::panelNavigationItems($panel) as $item) {
            $group = (string) ($item->getGroup() ?? '');
            if ($group === OltSidebarRegistry::GROUP_LABEL) {
                $existingUrls[(string) $item->getUrl()] = true;
            }
        }

        $toAdd = [];
        foreach (OltSidebarRegistry::navigationItems() as $item) {
            $url = (string) $item->getUrl();
            if ($url !== '' && ! isset($existingUrls[$url])) {
                $toAdd[] = $item;
            }
        }

        if ($toAdd !== []) {
            $panel->navigationItems($toAdd);
        }
    }

    /**
     * @return array<NavigationItem>
     */
    private static function panelNavigationItems(Panel $panel): array
    {
        $ref = new ReflectionClass($panel);
        $prop = $ref->getProperty('navigationItems');
        $prop->setAccessible(true);

        /** @var array<NavigationItem> $items */
        $items = $prop->getValue($panel);

        return $items;
    }

    /**
     * @param  array<NavigationItem>  $items
     */
    private static function setPanelNavigationItems(Panel $panel, array $items): void
    {
        $ref = new ReflectionClass($panel);
        $prop = $ref->getProperty('navigationItems');
        $prop->setAccessible(true);
        $prop->setValue($panel, $items);
    }

    /**
     * @return array<\Filament\Navigation\NavigationItem>
     */
    public static function allNavigationItems(): array
    {
        $merged = [];

        static::appendIf($merged, SuperadminQuickSidebarRegistry::hasVisibleEntries(), SuperadminQuickSidebarRegistry::navigationItems());
        static::appendIf($merged, ClientsSidebarNavigation::userCanSee(), ClientsSidebarRegistry::navigationItems());
        static::appendIf($merged, BillingSidebarNavigation::userCanSee(), BillingSidebarRegistry::navigationItems());
        static::appendIf($merged, InventorySidebarRegistry::hasVisibleEntries(), InventorySidebarRegistry::navigationItems());
        static::appendIf($merged, OltSidebarNavigation::userCanSee(), OltSidebarRegistry::navigationItems());
        static::appendIf($merged, PaymentsSidebarRegistry::hasVisibleEntries(), PaymentsSidebarRegistry::navigationItems());
        static::appendIf($merged, NetworkSidebarNavigation::userCanSee(), NetworkSidebarRegistry::navigationItems());
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
            OltSidebarRegistry::GROUP_LABEL, 'OLT' => 100,
            'Network' => 90,
            InventorySidebarRegistry::GROUP_LABEL => 40,
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
}
