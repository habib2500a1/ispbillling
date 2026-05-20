<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use App\Services\Reports\PaymentsReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.payments-report';

    protected static ?string $navigationLabel = 'Payment Reports';

    protected static ?string $title = 'Payments Report';

    protected static ?string $slug = 'payments-report';

    protected static bool $shouldRegisterNavigation = false;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $walletFilter = PaymentsReportService::WALLET_ALL;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && \App\Support\Rbac\StaffCapability::for($user)->canReports();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummaryProperty(): array
    {
        return app(PaymentsReportService::class)->summary(
            $this->periodFrom(),
            $this->periodTo(),
            $this->walletFilter,
        );
    }

    public function getWalletFilterLabelProperty(): string
    {
        return PaymentsReportService::walletFilterLabel($this->walletFilter);
    }

    public function getPeriodLabelProperty(): string
    {
        return $this->periodFrom()->format('d/m/y').' → '.$this->periodTo()->format('d/m/y');
    }

    public function applyFilters(): void
    {
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('paid_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid at')
                    ->dateTime('d/m/y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Client')
                    ->searchable(['customers.name', 'customers.customer_code'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('method')
                    ->label('Method')
                    ->formatStateUsing(fn (Payment $record): string => $record->methodLabel())
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->alignEnd()
                    ->color('primary')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->state(fn (Payment $record): string => number_format(PaymentsReportService::discountFor($record), 2))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('credited_to')
                    ->label('Credited to')
                    ->state(fn (Payment $record): string => PaymentsReportService::creditedToLabel($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('recorder.name')
                    ->label('Received by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Remarks')
                    ->state(fn (Payment $record): string => (string) ($record->notes ?: $record->reference ?: '—'))
                    ->limit(40)
                    ->tooltip(fn (Payment $record): ?string => $record->notes ?: $record->reference),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No payments in this period')
            ->emptyStateDescription('Adjust the date range or wallet filter, or record payments from billing.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    protected function getTableQuery(): Builder
    {
        return app(PaymentsReportService::class)->tableQuery(
            $this->periodFrom(),
            $this->periodTo(),
            $this->walletFilter,
        );
    }

    protected function exportCsv(): StreamedResponse
    {
        $rows = app(PaymentsReportService::class)->rowsForExport(
            $this->periodFrom(),
            $this->periodTo(),
            $this->walletFilter,
        );

        $filename = 'payments-report-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Paid at', 'Client', 'Customer code', 'Invoice', 'Method', 'Amount', 'Discount', 'Credited to', 'Received by', 'Remarks']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['paid_at'],
                    $row['client'],
                    $row['customer_code'],
                    $row['invoice'],
                    $row['method'],
                    $row['amount'],
                    $row['discount'],
                    $row['credited_to'],
                    $row['received_by'],
                    $row['remarks'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function periodFrom(): Carbon
    {
        return Carbon::parse($this->dateFrom ?: now()->startOfMonth())->startOfDay();
    }

    private function periodTo(): Carbon
    {
        return Carbon::parse($this->dateTo ?: now())->endOfDay();
    }
}
