<?php

namespace App\Filament\Resources\StoreDeviceLoanResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Pages\InventoryLoansOverdueReport;
use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\StoreDeviceLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStoreDeviceLoans extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = StoreDeviceLoanResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Technician asset tracking';
        $this->inventoryListSubtitle = 'Assigned equipment · return history · responsibility chain';
        $this->inventoryListCreateUrl = StoreDeviceLoanResource::getUrl('create');
        $this->inventoryListCreateLabel = 'Issue device';
        $this->inventoryListStats = [
            ['label' => 'Out on loan', 'value' => (string) ($m['support_out_count'] ?? 0), 'tone' => 'violet'],
            ['label' => 'Overdue', 'value' => (string) ($m['loans_overdue'] ?? 0), 'tone' => 'red'],
            ['label' => 'Due today', 'value' => (string) ($m['loans_due_today'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Returned', 'value' => (string) ($m['loans_returned'] ?? 0), 'tone' => 'emerald'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Overdue report', 'url' => InventoryLoansOverdueReport::getUrl()],
            ['label' => 'Device registry', 'url' => DeviceResource::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\CreateAction::make()->label('Issue device'),
        ];
    }
}
