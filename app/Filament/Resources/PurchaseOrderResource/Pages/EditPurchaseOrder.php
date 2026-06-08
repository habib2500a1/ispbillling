<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $po = $this->getRecord();
        $this->configureInventoryFormShell(
            $po->po_number,
            ucfirst((string) $po->status).' · '.$po->vendor?->name,
            PurchaseOrderResource::getUrl(),
            'Purchase orders',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\DeleteAction::make(),
        ];
    }
}
