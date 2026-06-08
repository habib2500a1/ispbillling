<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\IspOs\GlobalOperationsSearchService;
use App\Services\IspOs\IspOsIntelligenceService;
use App\Services\IspOs\NetworkTimelineService;
use App\Services\IspOs\RootCauseAnalysisService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class IspOsHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';

    protected static string $view = 'filament.pages.isp-os-hub';

    protected static ?string $navigationLabel = 'ISP OS';

    protected static ?string $title = '';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = -2;

    protected static ?string $slug = 'isp-os';

    public string $globalSearch = '';

    public function getTitle(): string
    {
        return '';
    }

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-os-module'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getIntelligence(): array
    {
        return $this->cachedHubStats(fn (): array => app(IspOsIntelligenceService::class)->payload());
    }

    /**
     * @return list<array{label: string, value: string, hint?: string, tone: string, url?: string}>
     */
    public function getKpiCards(): array
    {
        return app(IspOsIntelligenceService::class)->kpiCards();
    }

    /**
     * @return list<array{root_cause: string, confidence: float, message: string, tone: string}>
     */
    public function getRootCauses(): array
    {
        return app(RootCauseAnalysisService::class)->analyze();
    }

    /**
     * @return list<array{title: string, type: string, severity: string, at: string, sort: int}>
     */
    public function getTimeline(): array
    {
        return app(NetworkTimelineService::class)->recent();
    }

    /**
     * @return list<array{label: string, group: string, url: string, meta?: string}>
     */
    public function getSearchResults(): array
    {
        if (strlen(trim($this->globalSearch)) < 2) {
            return [];
        }

        return app(GlobalOperationsSearchService::class)->search($this->globalSearch);
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string}>
     */
    public function getModuleCards(): array
    {
        return [
            ['title' => 'Billing center', 'desc' => 'Invoices, collections, revenue', 'url' => BillingOverview::getUrl(), 'icon' => 'heroicon-o-banknotes', 'tone' => 'violet'],
            ['title' => 'Network center', 'desc' => 'MikroTik, SNMP, bandwidth', 'url' => NetworkIntelligenceHub::getUrl(), 'icon' => 'heroicon-o-cpu-chip', 'tone' => 'cyan'],
            ['title' => 'OLT operations', 'desc' => 'GPON, PON, optical NOC', 'url' => OltHub::getUrl(), 'icon' => 'heroicon-o-server-stack', 'tone' => 'indigo'],
            ['title' => 'Fiber GIS map', 'desc' => 'Routes, splitters, customers', 'url' => FiberPlantMap::getUrl(), 'icon' => 'heroicon-o-map', 'tone' => 'teal'],
            ['title' => 'Fault management', 'desc' => 'Active faults, RCA, alerts', 'url' => FaultManagementHub::getUrl(), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'rose'],
            ['title' => 'Field technicians', 'desc' => 'Tasks, visits, mobile tools', 'url' => FieldTechnicianCenter::getUrl(), 'icon' => 'heroicon-o-wrench-screwdriver', 'tone' => 'amber'],
            ['title' => 'Support & tickets', 'desc' => 'Open tickets, SLA, field visits', 'url' => SupportHub::getUrl(), 'icon' => 'heroicon-o-lifebuoy', 'tone' => 'sky'],
            ['title' => 'NOC wall', 'desc' => '24/7 large-screen monitoring', 'url' => NocWall::getUrl(), 'icon' => 'heroicon-o-tv', 'tone' => 'slate'],
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (
            StaffCapability::for(auth()->user())->canNetwork()
            || StaffCapability::for(auth()->user())->canBilling()
            || StaffCapability::for(auth()->user())->canSupport()
        );
    }
}
