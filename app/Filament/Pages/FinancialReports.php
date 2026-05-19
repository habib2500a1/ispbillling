<?php

namespace App\Filament\Pages;

use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\ChartOfAccountSeeder;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Services\Accounting\VatReportCsvExporter;
use Filament\Actions\Action;
use Filament\Forms\Form;
use Filament\Pages\Page;

class FinancialReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.financial-reports';

    protected static ?string $navigationLabel = 'P&L & VAT';

    protected static ?string $title = 'Financial reports';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]);
        app(ChartOfAccountSeeder::class)->seedForTenant();
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

  /**
     * @return array<string, mixed>
     */
    public function getReportData(): array
    {
        $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth());
        $to = Carbon::parse($this->data['to'] ?? now()->endOfMonth());
        $service = app(AccountingReportService::class);

        return [
            'from' => $from,
            'to' => $to,
            'pl' => $service->profitAndLoss($from, $to),
            'vat' => $service->vatReport($from, $to),
            'cashbook' => $service->cashbookSummary($from, $to),
            'snapshot' => $service->incomeExpenseSnapshot($from, $to),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportVatCsv')
                ->label('Export VAT CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): mixed {
                    $from = Carbon::parse($this->data['from'] ?? now()->startOfMonth());
                    $to = Carbon::parse($this->data['to'] ?? now()->endOfMonth());

                    return app(VatReportCsvExporter::class)->download($from, $to);
                }),
        ];
    }
}
