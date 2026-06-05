<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Filament\Resources\InventorySaleResource;
use App\Models\InventorySale;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryProductSalesReport extends Page implements HasTable
{
    use InteractsWithTable;
    use InventoryReportPage;

    protected static ?string $slug = 'inventory-report-product-sales';

    protected static string $view = 'filament.pages.inventory-table-report';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->mountInteractsWithTable();
    }

    public function getReportTitle(): string
    {
        return 'Product sales report';
    }

    public function getReportSubtitle(): string
    {
        return $this->dateFrom.' → '.$this->dateTo.' · completed POS / retail sales.';
    }

    public function getReportFiltersView(): \Illuminate\Contracts\View\View
    {
        return view('filament.pages.partials.inventory-date-filters', [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
        ]);
    }

    public function updatedDateFrom(): void
    {
        $this->resetTable();
    }

    public function updatedDateTo(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                return InventorySale::query()
                    ->where('status', 'completed')
                    ->whereDate('sold_at', '>=', $this->dateFrom)
                    ->whereDate('sold_at', '<=', $this->dateTo);
            })
            ->columns([
                Tables\Columns\TextColumn::make('sale_number')->label('Sale #')->searchable(),
                Tables\Columns\TextColumn::make('sold_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('customer_name')->label('Customer')->toggleable(),
                Tables\Columns\TextColumn::make('total')->money('BDT')->sortable(),
                Tables\Columns\TextColumn::make('gross_profit')->label('Profit')->money('BDT'),
                Tables\Columns\TextColumn::make('payment_method')->badge(),
                Tables\Columns\TextColumn::make('channel'),
            ])
            ->defaultSort('sold_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('pos')
                    ->label('New sale')
                    ->url(InventorySaleResource::getUrl('create'))
                    ->icon('heroicon-o-qr-code'),
            ]);
    }
}
