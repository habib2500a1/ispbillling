<?php

namespace App\Filament\Pages;

use App\Services\Dashboard\AiAnalyticsService;
use App\Services\IspOs\IspOsIntelligenceService;
use App\Services\Reports\AnalyticsReportService;
use App\Support\ReportsSidebarRegistry;
use Filament\Pages\Page;

class ReportsHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.reports-hub';

    protected static ?string $navigationLabel = 'Reports & analytics';

    protected static ?string $title = 'Reports & analytics';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 0;

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-bi-module'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $service = app(AnalyticsReportService::class);
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        return $service->summary($from, $to);
    }

    /**
     * Unified intelligence payload for the analytics home dashboard.
     *
     * @return array<string, mixed>
     */
    public function getDashboard(): array
    {
        $analytics = app(AnalyticsReportService::class);
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $duePro = $analytics->dueReportPro();
        $aging = $duePro['aging'];
        $overdue = round(
            (float) ($aging['days_1_30'] ?? 0)
            + (float) ($aging['days_31_60'] ?? 0)
            + (float) ($aging['days_61_plus'] ?? 0),
            2,
        );

        return [
            'summary' => $analytics->summary($from, $to),
            'revenue' => $analytics->revenueAnalytics(12),
            'intel' => app(IspOsIntelligenceService::class)->payload(),
            'ai' => app(AiAnalyticsService::class)->insights(),
            'overdue' => $overdue,
            'due_total' => (float) ($aging['total'] ?? 0),
        ];
    }

    /**
     * Searchable report catalog (existing routes only).
     *
     * @return list<array{domain: string, label: string, hint: string, url: string, icon: string, keywords: string}>
     */
    public function getReportCatalog(): array
    {
        $domains = [
            'reports_center' => 'Hub',
            'analytics' => 'Analytics',
            'monthly' => 'Revenue',
            'payment_reports' => 'Collection',
            'due_report' => 'Collection',
            'due_report_pro' => 'Collection',
            'churn_zone' => 'Customers',
            'area_wise' => 'GIS & Areas',
            'package_wise' => 'Customers',
            'export_clients' => 'Export',
            'print_reports' => 'Export',
            'btrc' => 'Regulatory',
            'gateway' => 'Collection',
        ];

        $hints = [
            'reports_center' => 'Analytics intelligence home',
            'analytics' => 'KPIs, charts, and tabbed analytics',
            'monthly' => 'Monthly revenue widgets and AR aging',
            'payment_reports' => 'Payments, discounts, CSV export',
            'due_report' => 'Outstanding invoices list',
            'due_report_pro' => 'Aging buckets and detailed balances',
            'churn_zone' => 'Zone collection and churn recovery',
            'area_wise' => 'Subscribers and dues by area',
            'package_wise' => 'Package popularity and MRR estimate',
            'export_clients' => 'Full customer CSV export',
            'print_reports' => 'Print-friendly PDF views',
            'btrc' => 'BTRC DIS regulatory export',
            'gateway' => 'bKash, Nagad, and gateway reconciliation',
        ];

        $catalog = [];

        foreach (ReportsSidebarRegistry::definitions() as $entry) {
            if (! ReportsSidebarRegistry::canSeeEntry($entry['key'])) {
                continue;
            }

            $catalog[] = [
                'domain' => $domains[$entry['key']] ?? 'Reports',
                'label' => $entry['label'],
                'hint' => $hints[$entry['key']] ?? $entry['label'],
                'url' => $entry['url'],
                'icon' => $entry['icon'],
                'keywords' => strtolower($entry['label'].' '.($domains[$entry['key']] ?? '').' '.($hints[$entry['key']] ?? '')),
            ];
        }

        $catalog[] = [
            'domain' => 'Analytics',
            'label' => 'AI analytics',
            'hint' => 'Churn risk, payment risk, fiber risk, forecast',
            'url' => AiAnalyticsDashboard::getUrl(),
            'icon' => 'heroicon-o-sparkles',
            'keywords' => 'ai analytics forecast churn risk optical',
        ];

        $catalog[] = [
            'domain' => 'Collection',
            'label' => 'Collection desk report',
            'hint' => 'Cash, online, collector breakdown',
            'url' => CollectionDeskReport::getUrl(),
            'icon' => 'heroicon-o-clipboard-document-list',
            'keywords' => 'collection desk cash collector',
        ];

        return $catalog;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && \App\Support\Rbac\StaffCapability::for($user)->canReports();
    }
}
