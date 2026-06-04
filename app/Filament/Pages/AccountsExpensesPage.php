<?php

namespace App\Filament\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Models\CollectorExpense;
use App\Models\VendorPayment;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsExpensesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static string $view = 'filament.pages.accounts-expenses';

    protected static ?string $navigationLabel = 'Expenses';

    protected static ?string $title = 'Expenses';

    protected static ?string $slug = 'accounts-expenses';

    protected static bool $shouldRegisterNavigation = false;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->mountInteractsWithTable();
    }

    public static function canAccess(): bool
    {
        return VendorPaymentResource::canViewAny();
    }

    public function getTotalExpensesProperty(): float
    {
        return round($this->vendorTotal + $this->collectorTotal, 2);
    }

    public function getVendorTotalProperty(): float
    {
        return (float) VendorPayment::query()
            ->whereBetween('payment_date', [$this->dateFrom, $this->dateTo])
            ->sum('amount');
    }

    public function getCollectorTotalProperty(): float
    {
        return (float) CollectorExpense::query()
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$this->dateFrom, $this->dateTo])
            ->sum('amount');
    }

    /**
     * @return \Illuminate\Support\Collection<int, CollectorExpense>
     */
    public function getCollectorExpensesProperty(): \Illuminate\Support\Collection
    {
        return CollectorExpense::query()
            ->with(['category', 'collector'])
            ->whereBetween('expense_date', [$this->dateFrom, $this->dateTo])
            ->orderByDesc('expense_date')
            ->limit(50)
            ->get();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')->label('Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('expense_type')
                    ->label('Type')
                    ->formatStateUsing(fn (VendorPayment $record): string => $record->typeLabel())
                    ->badge()
                    ->color(fn (VendorPayment $record): string => $record->isVendorExpense() ? 'info' : 'warning'),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Payee / vendor')
                    ->getStateUsing(fn (VendorPayment $record): string => $record->displayName()),
                Tables\Columns\TextColumn::make('payment_method')->label('Method')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('BDT')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('reference')->placeholder('—'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No vendor payments')
            ->emptyStateDescription('Use Add expense to record a new payment.');
    }

    protected function getTableQuery(): Builder
    {
        return VendorPayment::query()
            ->with('vendor')
            ->whereBetween('payment_date', [$this->dateFrom, $this->dateTo]);
    }
}
