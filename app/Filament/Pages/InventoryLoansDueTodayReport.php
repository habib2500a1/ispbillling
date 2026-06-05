<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Filament\Pages\Concerns\ListsStoreDeviceLoansTable;
use App\Models\StoreDeviceLoan;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class InventoryLoansDueTodayReport extends Page implements HasTable
{
    use InteractsWithTable;
    use InventoryReportPage;
    use ListsStoreDeviceLoansTable;

    protected static ?string $slug = 'inventory-report-loans-due-today';

    protected static string $view = 'filament.pages.inventory-table-report';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function getReportTitle(): string
    {
        return 'Due today';
    }

    public function getReportSubtitle(): string
    {
        return 'Support loans with return date today.';
    }

    public function table(Table $table): Table
    {
        return $this->storeDeviceLoanTable(
            $table,
            StoreDeviceLoan::query()->dueToday(),
        );
    }
}
