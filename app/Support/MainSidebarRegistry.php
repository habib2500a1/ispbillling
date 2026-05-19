<?php

namespace App\Support;

use App\Filament\Pages\AccountsHub;
use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\ClientsHub;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HrPayrollHub;
use App\Filament\Pages\InventoryHub;
use App\Filament\Pages\NetworkIntelligenceHub;
use App\Filament\Pages\NotificationsHub;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\ReportsHub;
use App\Filament\Pages\ResellersHub;
use App\Filament\Pages\SettingsHub;
use App\Filament\Pages\StaffControlHub;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\BandwidthClientResource;
use App\Filament\Resources\PaymentResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/**
 * Top-level module links — same "Main" menu on every admin page.
 */
final class MainSidebarRegistry
{
    public const GROUP = 'Main';

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   sort: int,
     *   url: string,
     *   active_routes: list<string>,
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'heroicon-o-home',
                'sort' => 0,
                'url' => Dashboard::getUrl(),
                'active_routes' => [
                    'filament.admin.pages.dashboard',
                    'filament.admin.pages.dashboard-hub',
                ],
            ],
            [
                'key' => 'clients',
                'label' => 'Clients',
                'icon' => 'heroicon-o-users',
                'sort' => 1,
                'url' => ClientsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.clients-hub'],
            ],
            [
                'key' => 'billing',
                'label' => 'Billing',
                'icon' => 'heroicon-o-document-text',
                'sort' => 2,
                'url' => BillCollectionDesk::getUrl(),
                'active_routes' => [
                    'filament.admin.pages.bill-collection-desk',
                    'filament.admin.pages.billing-overview',
                    'filament.admin.resources.invoices.*',
                ],
            ],
            [
                'key' => 'payments',
                'label' => 'Payments',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 3,
                'url' => PaymentResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.payments.*',
                    'filament.admin.resources.pending-gateway-payments.*',
                ],
            ],
            [
                'key' => 'network',
                'label' => 'Network',
                'icon' => 'heroicon-o-signal',
                'sort' => 4,
                'url' => NetworkIntelligenceHub::getUrl(),
                'active_routes' => ['filament.admin.pages.network-intelligence-hub'],
            ],
            [
                'key' => 'olt',
                'label' => 'OLT & Fiber',
                'icon' => 'heroicon-o-server-stack',
                'sort' => 5,
                'url' => OpticalMonitoringHub::getUrl(),
                'active_routes' => [
                    'filament.admin.pages.optical-noc',
                    'filament.admin.pages.olt-mac-table',
                ],
            ],
            [
                'key' => 'sms',
                'label' => 'SMS',
                'icon' => 'heroicon-o-chat-bubble-left-ellipsis',
                'sort' => 6,
                'url' => NotificationsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.notifications-hub'],
            ],
            [
                'key' => 'support',
                'label' => 'Support',
                'icon' => 'heroicon-o-lifebuoy',
                'sort' => 7,
                'url' => SupportHub::getUrl(),
                'active_routes' => ['filament.admin.pages.support-hub'],
            ],
            [
                'key' => 'reports',
                'label' => 'Reports',
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 8,
                'url' => ReportsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.reports-hub'],
            ],
            [
                'key' => 'accounts',
                'label' => 'Accounts',
                'icon' => 'heroicon-o-calculator',
                'sort' => 9,
                'url' => AccountsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-hub'],
            ],
            [
                'key' => 'resellers',
                'label' => 'Resellers',
                'icon' => 'heroicon-o-building-storefront',
                'sort' => 10,
                'url' => ResellersHub::getUrl(),
                'active_routes' => ['filament.admin.pages.resellers-hub'],
            ],
            [
                'key' => 'hrm',
                'label' => 'HRM',
                'icon' => 'heroicon-o-briefcase',
                'sort' => 11,
                'url' => HrPayrollHub::getUrl(),
                'active_routes' => ['filament.admin.pages.hr-payroll-hub'],
            ],
            [
                'key' => 'bw',
                'label' => 'BW Clients',
                'icon' => 'heroicon-o-arrows-right-left',
                'sort' => 12,
                'url' => BandwidthClientResource::getUrl(),
                'active_routes' => ['filament.admin.resources.bandwidth-clients.*'],
            ],
            [
                'key' => 'inventory',
                'label' => 'Inventory',
                'icon' => 'heroicon-o-cube',
                'sort' => 13,
                'url' => InventoryHub::getUrl(),
                'active_routes' => ['filament.admin.pages.inventory-hub'],
            ],
            [
                'key' => 'settings',
                'label' => 'Settings',
                'icon' => 'heroicon-o-cog-8-tooth',
                'sort' => 14,
                'url' => SettingsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.settings-hub'],
            ],
            [
                'key' => 'system',
                'label' => 'System',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort' => 15,
                'url' => StaffControlHub::getUrl(),
                'active_routes' => ['filament.admin.pages.staff-control-hub'],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null) {
            return [];
        }

        $items = [];

        foreach (self::definitions() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group(self::GROUP)
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });
        }

        return $items;
    }

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'dashboard' => Dashboard::canAccess(),
            'clients' => ClientsHub::canAccess(),
            'billing' => BillCollectionDesk::canAccess(),
            'payments' => PaymentResource::canViewAny(),
            'network' => NetworkIntelligenceHub::canAccess(),
            'olt' => OpticalMonitoringHub::canAccess(),
            'sms' => NotificationsHub::canAccess(),
            'support' => SupportHub::canAccess(),
            'reports' => ReportsHub::canAccess(),
            'accounts' => AccountsHub::canAccess(),
            'resellers' => ResellersHub::canAccess(),
            'hrm' => HrPayrollHub::canAccess(),
            'bw' => BandwidthClientResource::canViewAny(),
            'inventory' => InventoryHub::canAccess(),
            'settings' => SettingsHub::canAccess(),
            'system' => StaffControlHub::canAccess(),
            default => false,
        };
    }
}
