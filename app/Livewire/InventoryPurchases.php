<?php

namespace App\Livewire;

use App\Services\Inventory\InventoryPurchaseService;
use Livewire\Component;

class InventoryPurchases extends Component
{
    public string $filter = 'open';

    public string $tab = 'orders';

    public bool $showPoModal = false;

    public bool $showWhModal = false;

    public string $vendor_name = '';

    public ?int $warehouse_id = null;

    public string $notes = '';

    public string $po_status = 'ordered';

    /** @var list<array{product_id: string, quantity: string, unit_cost: string}> */
    public array $lines = [];

    public string $wh_code = '';

    public string $wh_name = '';

    public string $wh_address = '';

    public bool $wh_default = false;

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['admin.expenses', 'olt-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }

        $payload = app(InventoryPurchaseService::class)->payload();
        $this->warehouse_id = collect($payload['warehouses'])->firstWhere('is_default', true)['id']
            ?? (collect($payload['warehouses'])->first()['id'] ?? null);
        $this->resetLines();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function openPoModal(): void
    {
        $this->vendor_name = '';
        $this->notes = '';
        $this->po_status = 'ordered';
        $this->resetLines();
        $this->showPoModal = true;
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '1', 'unit_cost' => '0'];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->resetLines();
        }
    }

    public function updatedLines($value, string $key): void
    {
        // When product selected, fill unit cost from product list in render — handled client-side via save
    }

    public function savePo(): void
    {
        $this->validate([
            'vendor_name' => 'nullable|string|max:180',
            'warehouse_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            $items = [];
            foreach ($this->lines as $line) {
                $items[] = [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                    'unit_cost' => (float) ($line['unit_cost'] ?: 0),
                ];
            }

            app(InventoryPurchaseService::class)->createOrder([
                'vendor_name' => $this->vendor_name,
                'warehouse_id' => $this->warehouse_id,
                'notes' => $this->notes,
                'status' => $this->po_status,
                'items' => $items,
            ]);
            flash()->success(__('Purchase order created.'));
            $this->showPoModal = false;
            $this->tab = 'orders';
            $this->filter = 'open';
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function markOrdered(int $id): void
    {
        try {
            app(InventoryPurchaseService::class)->markOrdered($id);
            flash()->success(__('Marked as ordered.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function receive(int $id): void
    {
        try {
            app(InventoryPurchaseService::class)->receive($id);
            flash()->success(__('PO received — stock updated.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function cancel(int $id): void
    {
        try {
            app(InventoryPurchaseService::class)->cancel($id);
            flash()->success(__('PO cancelled.'));
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function openWhModal(): void
    {
        $this->wh_code = '';
        $this->wh_name = '';
        $this->wh_address = '';
        $this->wh_default = false;
        $this->showWhModal = true;
    }

    public function saveWarehouse(): void
    {
        $this->validate([
            'wh_name' => 'required|string|max:180',
            'wh_code' => 'nullable|string|max:32',
            'wh_address' => 'nullable|string|max:255',
        ]);

        try {
            app(InventoryPurchaseService::class)->saveWarehouse(null, [
                'code' => $this->wh_code,
                'name' => $this->wh_name,
                'address' => $this->wh_address,
                'is_default' => $this->wh_default,
            ]);
            flash()->success(__('Warehouse saved.'));
            $this->showWhModal = false;
            $this->tab = 'warehouses';
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function refresh(): void
    {
        flash()->success(__('Purchases refreshed.'));
    }

    private function resetLines(): void
    {
        $this->lines = [
            ['product_id' => '', 'quantity' => '1', 'unit_cost' => '0'],
        ];
    }

    public function render()
    {
        $data = app(InventoryPurchaseService::class)->payload($this->filter);

        return view('livewire.inventory-purchases', $data)->layout('layouts.app');
    }
}
