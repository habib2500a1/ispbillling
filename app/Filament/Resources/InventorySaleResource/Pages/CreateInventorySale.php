<?php

namespace App\Filament\Resources\InventorySaleResource\Pages;

use App\Filament\Resources\InventorySaleResource;
use App\Models\Product;
use App\Services\Inventory\InventorySaleService;
use App\Services\Inventory\ProductBarcodeLookup;
use App\Support\TenantResolver;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInventorySale extends CreateRecord
{
    protected static string $resource = InventorySaleResource::class;

    protected static string $view = 'filament.resources.inventory-sale-resource.pages.create-inventory-sale';

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function appendProductToSale(int $productId, bool $notify = false): void
    {
        $product = Product::query()
            ->where('is_active', true)
            ->find($productId);

        if ($product === null) {
            Notification::make()->title('Product not found')->warning()->send();

            return;
        }

        $increased = $this->mergeProductIntoLines($product);
        $state = $this->form->getState();
        $this->form->fill([
            ...$state,
            'barcode_scan' => $state['barcode_scan'] ?? '',
        ]);

        if ($notify) {
            Notification::make()
                ->title($product->name)
                ->body($increased ? 'Quantity +1' : 'Line added')
                ->success()
                ->send();
        }
    }

    public function appendScannedBarcode(?string $scan): void
    {
        $scan = trim((string) $scan);
        if ($scan === '') {
            return;
        }

        $product = app(ProductBarcodeLookup::class)->find(TenantResolver::requiredTenantId(), $scan);

        if ($product === null) {
            Notification::make()
                ->title('Product not found')
                ->body('No match for: '.$scan)
                ->warning()
                ->send();

            return;
        }

        $this->mergeProductIntoLines($product);
        $state = $this->form->getState();
        $this->form->fill([
            ...$state,
            'lines' => $state['lines'] ?? [],
            'barcode_scan' => '',
        ]);

        Notification::make()
            ->title($product->name)
            ->body('Added to sale')
            ->success()
            ->send();
    }

    private function mergeProductIntoLines(Product $product): bool
    {
        $state = $this->form->getState();
        $lines = $state['lines'] ?? [];

        foreach ($lines as $index => $line) {
            if ((int) ($line['product_id'] ?? 0) === (int) $product->id) {
                $lines[$index]['quantity'] = (int) ($line['quantity'] ?? 1) + 1;
                $this->form->fill([...$state, 'lines' => $lines]);

                return true;
            }
        }

        foreach ($lines as $index => $line) {
            if (blank($line['product_id'] ?? null)) {
                $lines[$index] = [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => $product->effectiveSellPrice(),
                ];
                $this->form->fill([...$state, 'lines' => $lines]);

                return false;
            }
        }

        $lines[] = [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $product->effectiveSellPrice(),
        ];
        $this->form->fill([...$state, 'lines' => $lines]);

        return false;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            if (empty($line['product_id'])) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
            ];
        }

        $sale = app(InventorySaleService::class)->recordSale(
            tenantId: (int) auth()->user()->tenant_id,
            lines: $lines,
            channel: (string) ($data['channel'] ?? 'counter'),
            customerName: $data['customer_name'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            discount: (float) ($data['discount'] ?? 0),
            paymentMethod: (string) ($data['payment_method'] ?? 'cash'),
            notes: $data['notes'] ?? null,
            user: auth()->user(),
            warehouseId: isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            barcodeScan: null,
        );

        $walletNote = in_array($sale->payment_method, config('inventory.staff_collector_cash_methods', ['cash', 'counter']), true)
            ? ' · Full '.number_format((float) $sale->total, 2).' BDT in your cash-in-hand (settle later)'
            : '';

        Notification::make()
            ->title('Sale recorded')
            ->body($sale->sale_number.' · Profit '.number_format((float) $sale->gross_profit, 2).' BDT · GL posted'.$walletNote)
            ->success()
            ->send();

        return $sale;
    }

    protected function getRedirectUrl(): string
    {
        return InventorySaleResource::getUrl('view', ['record' => $this->record]).'?print=1';
    }
}
