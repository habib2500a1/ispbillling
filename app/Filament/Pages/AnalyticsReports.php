<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use App\Support\BandwidthDirection;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class AnalyticsReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.pages.analytics-reports';

    protected static ?string $navigationLabel = 'Analytics dashboard';

    protected static ?string $title = 'Reporting & analytics';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $activeTab = 'collection';

    public string $activeDomain = 'revenue';

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);

        $tab = request()->query('tab');
        if (is_string($tab)) {
            $this->setActiveTab($tab);
        }

        $this->syncDomainFromTab();
    }

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-bi-module'];
    }

    public function applyDatePreset(string $preset): void
    {
        $this->data = match ($preset) {
            'today' => [
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ],
            'week' => [
                'from' => now()->startOfWeek()->toDateString(),
                'to' => now()->endOfWeek()->toDateString(),
            ],
            'month' => [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ],
            'year' => [
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->endOfYear()->toDateString(),
            ],
            default => $this->data ?? [],
        };

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->label('From')->required()->live(),
                DatePicker::make('to')->label('To')->required()->live(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, [
            'collection', 'due', 'revenue', 'churn', 'growth', 'online', 'area', 'packages',
        ], true)) {
            $this->activeTab = $tab;
            $this->syncDomainFromTab();
        }
    }

    public function setActiveDomain(string $domain): void
    {
        if (! in_array($domain, ['revenue', 'customers', 'network', 'gis'], true)) {
            return;
        }

        $this->activeDomain = $domain;

        $this->activeTab = match ($domain) {
            'revenue' => 'collection',
            'customers' => 'growth',
            'network' => 'online',
            'gis' => 'area',
            default => $this->activeTab,
        };
    }

    private function syncDomainFromTab(): void
    {
        $this->activeDomain = match ($this->activeTab) {
            'collection', 'due', 'revenue' => 'revenue',
            'churn', 'growth', 'packages' => 'customers',
            'online' => 'network',
            'area' => 'gis',
            default => $this->activeDomain,
        };
    }

    /**
     * @return list<array{key: string, label: string, export_url: string|null, export_label: string|null}>
     */
    public function getTabDefinitions(): array
    {
        return [
            'collection' => [
                'key' => 'collection',
                'label' => 'Collection',
                'export_url' => PaymentsReport::getUrl(),
                'export_label' => 'Full payments CSV',
            ],
            'due' => [
                'key' => 'due',
                'label' => 'Due',
                'export_url' => DueReportProPage::getUrl(),
                'export_label' => 'Due pro export',
            ],
            'revenue' => [
                'key' => 'revenue',
                'label' => 'Revenue',
                'export_url' => BillingReports::getUrl(),
                'export_label' => 'Monthly billing',
            ],
            'churn' => [
                'key' => 'churn',
                'label' => 'Churn',
                'export_url' => ChurnZoneReports::getUrl(),
                'export_label' => 'Zone churn report',
            ],
            'growth' => [
                'key' => 'growth',
                'label' => 'Growth',
                'export_url' => ExportClientsReport::getUrl(),
                'export_label' => 'Export clients',
            ],
            'online' => [
                'key' => 'online',
                'label' => 'Online users',
                'export_url' => null,
                'export_label' => null,
            ],
            'area' => [
                'key' => 'area',
                'label' => 'Area-wise',
                'export_url' => AreaWiseClientsReport::getUrl(),
                'export_label' => 'Area CSV',
            ],
            'packages' => [
                'key' => 'packages',
                'label' => 'Packages',
                'export_url' => PackageWiseReportPage::getUrl(),
                'export_label' => 'Package CSV',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportData(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now()->endOfMonth())->endOfDay();
        $service = app(AnalyticsReportService::class);

        return [
            'from' => $from,
            'to' => $to,
            'summary' => $service->summary($from, $to),
            'collection' => $service->collectionReport($from, $to),
            'due' => $service->dueReport(),
            'revenue' => $service->revenueAnalytics(12),
            'churn' => $service->churnAnalysis($from, $to),
            'growth' => $service->subscriberGrowth(12),
            'online' => $service->onlineUserReport(),
            'area' => $service->areaWiseReport(),
            'packages' => $service->packagePopularity(),
        ];
    }

    public static function formatBps(?int $bps): string
    {
        return BandwidthDirection::formatBps($bps);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && \App\Support\Rbac\StaffCapability::for($user)->canReports();
    }
}
