<?php

namespace App\Filament\Pages;

use App\Services\Network\NetflowAnalysisService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class NetflowAnalysis extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.netflow-analysis';

    protected static ?string $navigationLabel = 'NetFlow analysis';

    protected static ?string $title = 'NetFlow analysis';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = ['hours' => 24];

    public function mount(): void
    {
        $this->form->fill(['hours' => 24]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('hours')
                ->label('Period')
                ->options([
                    1 => 'Last 1 hour',
                    6 => 'Last 6 hours',
                    24 => 'Last 24 hours',
                    168 => 'Last 7 days',
                ])
                ->required()
                ->live(),
        ])->statePath('data');
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $hours = (int) ($this->data['hours'] ?? 24);

        return app(NetflowAnalysisService::class)->summary(null, $hours);
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
