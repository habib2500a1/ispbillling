<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class DashboardHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.dashboard-hub';

    protected static ?string $navigationLabel = 'Dashboard hub';

    protected static ?string $title = 'Dashboard hub';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        foreach ((new static)->getDashboardCards() as $card) {
            if ($card['visible']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{title: string, description: string, url: string, tone: string, icon: string, visible: bool}>
     */
    public function getDashboardCards(): array
    {
        return [
            [
                'title' => 'Command center',
                'description' => 'Executive KPI wall, revenue & lifecycle',
                'url' => Dashboard::getUrl(),
                'tone' => 'indigo',
                'icon' => 'heroicon-o-squares-2x2',
                'visible' => Dashboard::canAccess(),
            ],
            [
                'title' => 'NOC dashboard',
                'description' => 'Live network, bandwidth, routers, GPON health',
                'url' => NocDashboard::getUrl(),
                'tone' => 'cyan',
                'icon' => 'heroicon-o-signal',
                'visible' => NocDashboard::canAccess(),
            ],
            [
                'title' => 'Billing dashboard',
                'description' => 'Collections, dues, invoices, payment desk',
                'url' => BillingDashboard::getUrl(),
                'tone' => 'emerald',
                'icon' => 'heroicon-o-banknotes',
                'visible' => BillingDashboard::canAccess(),
            ],
            [
                'title' => 'GPON / ONU',
                'description' => 'Signal health, OLT, fiber alerts, topology',
                'url' => GponDashboard::getUrl(),
                'tone' => 'violet',
                'icon' => 'heroicon-o-cpu-chip',
                'visible' => GponDashboard::canAccess(),
            ],
            [
                'title' => 'MikroTik',
                'description' => 'PPPoE sessions, router status, traffic',
                'url' => MikrotikDashboard::getUrl(),
                'tone' => 'slate',
                'icon' => 'heroicon-o-server',
                'visible' => MikrotikDashboard::canAccess(),
            ],
            [
                'title' => 'Support dashboard',
                'description' => 'Tickets, SLA breaches, technician load',
                'url' => SupportDashboard::getUrl(),
                'tone' => 'amber',
                'icon' => 'heroicon-o-lifebuoy',
                'visible' => SupportDashboard::canAccess(),
            ],
            [
                'title' => 'Support center',
                'description' => 'Full support hub & tools',
                'url' => SupportHub::getUrl(),
                'tone' => 'amber',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'visible' => SupportHub::canAccess(),
            ],
            [
                'title' => 'Accounting hub',
                'description' => 'Ledger, vouchers, financial reports',
                'url' => AccountingHub::getUrl(),
                'tone' => 'rose',
                'icon' => 'heroicon-o-calculator',
                'visible' => AccountingHub::canAccess(),
            ],
        ];
    }
}
