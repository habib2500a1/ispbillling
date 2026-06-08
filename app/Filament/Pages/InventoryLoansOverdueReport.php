<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Filament\Pages\Concerns\UsesInventoryReportLayout;
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
    use UsesInventoryReportLayout;

    protected static ?string $slug = 'inventory-report-loans-overdue';

    protected static string $view = 'filament.pages.inventory-table-report';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
        $this->mountInventoryReportLayout();
    }

    /**
     * @return list<array{label: string, value: string, tone: string}>
     */
    public function getReportStats(): array
    {
        $m = $this->inventorySummary;

        return [
            ['label' => 'Overdue', 'value' => (string) ($m['loans_overdue'] ?? 0), 'tone' => 'rose'],
            ['label' => 'Devices out', 'value' => (string) ($m['support_out_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Returned', 'value' => (string) ($m['loans_returned'] ?? 0), 'tone' => 'teal'],
            ['label' => 'Due today', 'value' => (string) ($m['loans_due_today'] ?? 0), 'tone' => 'sky'],
        ];
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
