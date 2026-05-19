<?php

namespace App\Filament\Pages;

use App\Services\Accounting\AccountingReportService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class AccountsIncomeVsExpensePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.accounts-income-vs-expense';

    protected static ?string $navigationLabel = 'Income vs expense';

    protected static ?string $title = 'Income vs expense';

    protected static ?string $slug = 'accounts-income-vs-expense';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check();
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
     * @return array<string, mixed>
     */
    public function getReportProperty(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->data['to'] ?? now())->endOfDay();
        $service = app(AccountingReportService::class);

        $pl = $service->profitAndLoss($from, $to);
        $snap = $service->incomeExpenseSnapshot($from, $to);

        $income = (float) $pl['income'];
        $expenses = (float) $pl['expenses'];
        $total = $income + $expenses;

        return [
            'pl' => $pl,
            'snap' => $snap,
            'income_pct' => $total > 0 ? round(($income / $total) * 100, 1) : 50,
        ];
    }
}
