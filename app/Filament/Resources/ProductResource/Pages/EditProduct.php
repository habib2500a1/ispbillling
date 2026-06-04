<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.edit-product';

    public function getSubheading(): ?string
    {
        return 'Update pricing, shop visibility, or use Adjust stock from the products list.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('inventory_hub')
                ->label('Inventory center')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(InventoryHub::getUrl()),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $sell = (float) ($data['sell_price'] ?? 0);
        $cost = (float) ($data['cost_price'] ?? 0);

        if ($sell > 0.009) {
            $data['unit_price'] = $sell;
        } elseif ($cost > 0.009 && (float) ($data['unit_price'] ?? 0) < 0.01) {
            $data['unit_price'] = $cost;
        }

        return $data;
    }
}
