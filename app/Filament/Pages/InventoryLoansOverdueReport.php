<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Filament\Pages\Concerns\ListsStoreDeviceLoansTable;
use App\Models\StoreDeviceLoan;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class InventoryLoansOverdueReport extends Page implements HasTable
{
    use InteractsWithTable;
    use InventoryReportPage;
    use ListsStoreDeviceLoansTable;

    protected static ?string $slug = 'inventory-report-loans-overdue';

    protected static string $view = 'filament.pages.inventory-table-report';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function getReportTitle(): string
    {
        return 'Overdue returns';
    }

    public function getReportSubtitle(): string
    {
        return 'Issued devices past their due return date.';
    }

    public function table(Table $table): Table
    {
        return $this->storeDeviceLoanTable(
            $table,
            StoreDeviceLoan::query()->overdue(),
        );
    }
}
