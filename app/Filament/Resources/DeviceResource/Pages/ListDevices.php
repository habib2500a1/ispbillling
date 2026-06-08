<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Pages\InventoryWarrantyManagement;
use App\Filament\Resources\DeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = DeviceResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Network devices';
        $this->inventoryListSubtitle = 'ONU / CPE · assignment · warranty · optical';
        $this->inventoryListCreateUrl = DeviceResource::getUrl('create');
        $this->inventoryListCreateLabel = 'Register device';
        $this->inventoryListStats = [
            ['label' => 'Total devices', 'value' => (string) ($m['total_devices'] ?? 0), 'tone' => 'teal'],
            ['label' => 'Assigned', 'value' => (string) ($m['assigned_assets'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Warranty (30d)', 'value' => (string) ($m['warranty_expiring'] ?? 0), 'tone' => 'rose'],
            ['label' => 'Damaged', 'value' => (string) ($m['damaged_assets'] ?? 0), 'tone' => 'sky'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Warranty desk', 'url' => InventoryWarrantyManagement::getUrl()],
            ['label' => 'Support loans', 'url' => \App\Filament\Resources\StoreDeviceLoanResource::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\CreateAction::make()->label('Register device'),
        ];
    }
}
