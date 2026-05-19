<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsIncomePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string $view = 'filament.pages.accounts-income';

    protected static ?string $navigationLabel = 'Income';

    protected static ?string $title = 'Income';

    protected static ?string $slug = 'accounts-income';

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
        return auth()->check();
    }

    public function getTotalIncomeProperty(): float
    {
        return (float) $this->getTableQuery()->sum('amount');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('paid_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label('Date')->dateTime('d/m/y H:i')->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Client')->searchable(),
                Tables\Columns\TextColumn::make('method')->label('Method')->badge(),
                Tables\Columns\TextColumn::make('amount')->money('BDT')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('recorder.name')->label('Received by')->placeholder('—'),
            ])
            ->paginated([25, 50, 100]);
    }

    protected function getTableQuery(): Builder
    {
        return Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [
                \Carbon\Carbon::parse($this->dateFrom)->startOfDay(),
                \Carbon\Carbon::parse($this->dateTo)->endOfDay(),
            ])
            ->with(['customer', 'recorder']);
    }
}
