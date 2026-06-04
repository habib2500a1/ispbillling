<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Pages\InventoryHub;
use App\Filament\Resources\ProductResource;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryStockService;
use App\Support\ProductSkuGenerator;
use App\Support\TenantResolver;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.create-product';

    private int $openingStockQty = 0;

    private ?int $openingWarehouseId = null;

    public function getTitle(): string
    {
        return 'New product';
    }

    public function getSubheading(): ?string
    {
        return 'Barcode · pricing · optional opening stock into a warehouse.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('inventory_hub')
                ->label('Inventory center')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(InventoryHub::getUrl()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $state = $this->form->getState();

        $this->openingStockQty = max(0, (int) ($state['opening_stock_qty'] ?? 0));
        $this->openingWarehouseId = filled($state['opening_warehouse_id'] ?? null)
            ? (int) $state['opening_warehouse_id']
            : null;

        if (blank($data['sku'] ?? null)) {
            $data['sku'] = ProductSkuGenerator::generate($tenantId, (string) ($data['name'] ?? ''));
        }

        $sell = (float) ($data['sell_price'] ?? 0);
        $cost = (float) ($data['cost_price'] ?? 0);
        $data['unit_price'] = $sell > 0.009 ? $sell : ($cost > 0.009 ? $cost : 0);

        return $data;
    }

    protected function afterCreate(): void
    {
        $product = $this->getRecord();
        $this->relocateImageFromDraftFolder($product);

        if ($this->openingStockQty < 1) {
            return;
        }

        $unitCost = $product->effectiveCost();

        app(InventoryStockService::class)->adjustStock(
            $product,
            $this->openingStockQty,
            StockMovement::TYPE_ADJUSTMENT_IN,
            $unitCost,
            $product->effectiveSellPrice(),
            null,
            null,
            'Opening stock on product create',
            auth()->user(),
            $this->openingWarehouseId,
        );

        Notification::make()
            ->title('Opening stock recorded')
            ->body($this->openingStockQty.' '.$product->unit.' added to warehouse.')
            ->success()
            ->send();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Product created';
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    private function relocateImageFromDraftFolder(\App\Models\Product $product): void
    {
        $path = (string) ($product->image_path ?? '');
        if ($path === '' || ! str_contains($path, '/draft/')) {
            return;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return;
        }

        $newPath = str_replace('/draft/', '/'.$product->getKey().'/', $path);
        $disk->makeDirectory(dirname($newPath));
        $disk->move($path, $newPath);
        $product->updateQuietly(['image_path' => $newPath]);
        \App\Services\Inventory\InventoryDashboardService::flushSummaryCache((int) $product->tenant_id);
    }
}
