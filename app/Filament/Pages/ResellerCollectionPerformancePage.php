<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ResellerResource;
use App\Services\Resellers\ResellerCollectionPerformanceService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ResellerCollectionPerformancePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.reseller-collection-performance';

    protected static ?string $navigationLabel = 'Collection performance';

    protected static ?string $title = 'Reseller collection performance';

    protected static ?string $slug = 'reseller-collection-performance';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ResellerResource::canViewAny();
    }

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')->required()->live(),
                DatePicker::make('to')->required()->live(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now())->endOfDay();

        return app(ResellerCollectionPerformanceService::class)->partnerRows($from, $to);
    }

    /**
     * @return array<string, float|int>
     */
    public function getTotalsProperty(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now())->endOfDay();

        return app(ResellerCollectionPerformanceService::class)->networkTotals($from, $to);
    }
}
