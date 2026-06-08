<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\IspOs\GlobalOperationsSearchService;
use App\Services\IspOs\IspOsExecutiveDashboardService;
use App\Services\IspOs\RootCauseAnalysisService;
use App\Services\IspOs\NetworkTimelineService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class IspOsHub extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';

    protected static string $view = 'filament.pages.isp-os-hub';

    protected static ?string $navigationLabel = 'ISP OS';

    protected static ?string $title = 'ISP Operating System';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = -2;

    protected static ?string $slug = 'isp-os';

    /** @var array<string, mixed> */
    public array $executive = [];

    public string $globalSearch = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $activeTab = 'executive';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->refreshExecutive();
    }

    public function refreshExecutive(): void
    {
        $this->executive = app(IspOsExecutiveDashboardService::class)->snapshot();
        $this->hubStatsCache = null;
    }

    public function updatedGlobalSearch(): void
    {
        if (strlen(trim($this->globalSearch)) < 2) {
            $this->searchResults = [];

            return;
        }
        $this->searchResults = app(GlobalOperationsSearchService::class)->search($this->globalSearch);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $ex = $this->executive;
        $intel = $ex['intelligence'] ?? [];

        return [
            'executive' => $ex,
            'intel' => $intel,
            'networkKpis' => $ex['network_kpis'] ?? [],
            'executiveKpis' => $ex['executive_kpis'] ?? [],
            'commandCenters' => $ex['command_centers'] ?? [],
            'operationsModules' => $ex['operations_modules'] ?? [],
            'insights' => $intel['insights'] ?? [],
            'rca' => app(RootCauseAnalysisService::class)->analyze(),
            'timeline' => app(NetworkTimelineService::class)->recent(),
            'mobileLinks' => [
                ['url' => BillingOverview::getUrl(), 'icon' => 'banknotes', 'label' => 'Billing'],
                ['url' => ClientsHub::getUrl(), 'icon' => 'users', 'label' => 'Clients'],
                ['url' => SupportHub::getUrl(), 'icon' => 'lifebuoy', 'label' => 'Tickets'],
                ['url' => FiberPlantMap::getUrl(), 'icon' => 'map', 'label' => 'GIS'],
                ['url' => AiOperationsCopilotHub::getUrl(), 'icon' => 'sparkles', 'label' => 'AI'],
            ],
        ];
    }

    public function getTitle(): string
    {
        return 'ISP Operating System';
    }

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-os-module'];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $cap = StaffCapability::for($user);

        return $cap->canNetwork()
            || $cap->canBilling()
            || $cap->canSupport()
            || $cap->canAccounting()
            || $cap->canHrm()
            || $cap->isTenantAdmin();
    }
}
