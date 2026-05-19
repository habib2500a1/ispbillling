<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ChurnZoneReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.churn-zone-reports';

    protected static ?string $navigationLabel = 'Churn & zone collection';

    protected static ?string $title = 'Churn & zone collection';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $activeTab = 'zones';

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);
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
        if (in_array($tab, ['zones', 'churn'], true)) {
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
            'zones' => $service->zoneCollectionReport($from, $to),
            'churn' => $service->churnByZoneReport($from, $to),
            'summary' => $service->summary($from, $to),
        ];
    }

    public static function canAccess(): bool
    {
        return ReportsHub::canAccess();
    }
}
