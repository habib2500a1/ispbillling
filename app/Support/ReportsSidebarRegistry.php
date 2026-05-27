<?php

namespace App\Support;

use App\Filament\Pages\AnalyticsReports;
use App\Filament\Pages\AreaWiseClientsReport;
use App\Filament\Pages\BillingReports;
use App\Filament\Pages\BtrcReport;
use App\Filament\Pages\ChurnZoneReports;
use App\Filament\Pages\DueReportPage;
use App\Filament\Pages\DueReportProPage;
use App\Filament\Pages\ExportClientsReport;
use App\Filament\Pages\GatewayReconciliationReport;
use App\Filament\Pages\PackageWiseReportPage;
use App\Filament\Pages\PaymentsReport;
use App\Filament\Pages\PrintReportsHub;
use App\Filament\Pages\ReportsHub;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class ReportsSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'reports_center',
                'label' => 'Reports center',
                'icon' => 'heroicon-o-presentation-chart-line',
                'sort' => 0,
                'url' => ReportsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.reports-hub'],
            ],
            [
                'key' => 'due_report',
                'label' => 'Due Report',
                'icon' => 'heroicon-o-exclamation-triangle',
                'sort' => 1,
                'url' => DueReportPage::getUrl(),
                'active_routes' => ['filament.admin.pages.due-report'],
            ],
            [
                'key' => 'due_report_pro',
                'label' => 'Due Report Pro',
                'icon' => 'heroicon-o-shield-exclamation',
                'sort' => 3,
                'url' => DueReportProPage::getUrl(),
                'active_routes' => ['filament.admin.pages.due-report-pro'],
            ],
            [
                'key' => 'payment_reports',
                'label' => 'Payment Reports',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 4,
                'url' => PaymentsReport::getUrl(),
                'active_routes' => ['filament.admin.pages.payments-report'],
            ],
            [
                'key' => 'export_clients',
                'label' => 'Export Clients',
                'icon' => 'heroicon-o-arrow-down-tray',
                'sort' => 5,
                'url' => ExportClientsReport::getUrl(),
                'active_routes' => ['filament.admin.pages.export-clients-report'],
            ],
            [
                'key' => 'print_reports',
                'label' => 'Print Reports',
                'icon' => 'heroicon-o-printer',
                'sort' => 6,
                'url' => PrintReportsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.print-reports'],
            ],
            [
                'key' => 'area_wise',
                'label' => 'Area-wise Client',
                'icon' => 'heroicon-o-map-pin',
                'sort' => 7,
                'url' => AreaWiseClientsReport::getUrl(),
                'active_routes' => ['filament.admin.pages.area-wise-clients-report'],
            ],
            [
                'key' => 'package_wise',
                'label' => 'Package-wise Report',
                'icon' => 'heroicon-o-rectangle-stack',
                'sort' => 8,
                'url' => PackageWiseReportPage::getUrl(),
                'active_routes' => ['filament.admin.pages.package-wise-report'],
            ],
            [
                'key' => 'btrc',
                'label' => 'BTRC Report',
                'icon' => 'heroicon-o-document-arrow-down',
                'sort' => 9,
                'url' => BtrcReport::getUrl(),
                'active_routes' => ['filament.admin.pages.btrc-report'],
            ],
            [
                'key' => 'analytics',
                'label' => 'Analytics dashboard',
                'icon' => 'heroicon-o-chart-pie',
                'sort' => 10,
                'url' => AnalyticsReports::getUrl(),
                'active_routes' => ['filament.admin.pages.analytics-reports'],
            ],
            [
                'key' => 'monthly',
                'label' => 'Monthly revenue',
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 11,
                'url' => BillingReports::getUrl(),
                'active_routes' => ['filament.admin.pages.billing-reports'],
            ],
            [
                'key' => 'gateway',
                'label' => 'Gateway reconciliation',
                'icon' => 'heroicon-o-scale',
                'sort' => 12,
                'url' => GatewayReconciliationReport::getUrl(),
                'active_routes' => ['filament.admin.pages.gateway-reconciliation-report'],
            ],
            [
                'key' => 'churn_zone',
                'label' => 'Churn & zone collection',
                'icon' => 'heroicon-o-map',
                'sort' => 13,
                'url' => ChurnZoneReports::getUrl(),
                'active_routes' => ['filament.admin.pages.churn-zone-reports'],
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
                ->group('Reports')
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
            'reports_center' => ReportsHub::canAccess(),
            'monthly' => BillingReports::canAccess(),
            'gateway' => GatewayReconciliationReport::canAccess(),
            'btrc' => BtrcReport::canAccess(),
            default => PaymentsReport::canAccess(),
        };
    }
}
