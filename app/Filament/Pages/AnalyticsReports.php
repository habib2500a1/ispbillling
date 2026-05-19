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
        }
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
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->hasRole('isp-manager'));
    }
}
