<?php

namespace App\Livewire;

use App\Models\InventoryProduct;
use App\Services\Inventory\InventoryHubService;
use Livewire\Component;

class InventoryHub extends Component
{
    public string $search = '';

    public string $filter = 'all';

    public bool $showProductModal = false;

    public bool $showMoveModal = false;

    public ?int $editId = null;

    public ?int $moveProductId = null;

    public string $sku = '';

    public string $name = '';

    public string $category = 'onu';

    public string $unit = 'pcs';

    public string $stock_qty = '0';

    public string $reorder_level = '0';

    public string $cost_price = '0';

    public string $sell_price = '0';

    public bool $is_active = true;

    public string $notes = '';

    public string $move_type = 'in';

    public string $move_qty = '1';

    public string $move_reference = '';

    public string $move_notes = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['admin.expenses', 'olt-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function openCreate(): void
    {
        $this->resetProductForm();
        $this->editId = null;
        $this->showProductModal = true;
    }

    public function openEdit(int $id): void
    {
        $p = InventoryProduct::query()->findOrFail($id);
        $this->editId = $p->id;
        $this->sku = (string) ($p->sku ?? '');
        $this->name = $p->name;
        $this->category = $p->category ?: 'other';
        $this->unit = $p->unit ?: 'pcs';
        $this->stock_qty = (string) $p->stock_qty;
        $this->reorder_level = (string) $p->reorder_level;
        $this->cost_price = (string) $p->cost_price;
        $this->sell_price = (string) $p->sell_price;
        $this->is_active = (bool) $p->is_active;
        $this->notes = (string) ($p->notes ?? '');
        $this->showProductModal = true;
    }

    public function saveProduct(): void
    {
        $this->validate([
            'name' => 'required|string|max:180',
            'sku' => 'nullable|string|max:64',
            'category' => 'nullable|string|max:64',
            'unit' => 'nullable|string|max:24',
            'reorder_level' => 'nullable|integer|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sell_price' => 'nullable|numeric|min:0',
            'stock_qty' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            app(InventoryHubService::class)->saveProduct($this->editId, [
                'sku' => $this->sku,
                'name' => $this->name,
                'category' => $this->category,
                'unit' => $this->unit,
                'reorder_level' => (int) $this->reorder_level,
                'cost_price' => $this->cost_price,
                'sell_price' => $this->sell_price,
                'is_active' => $this->is_active,
                'notes' => $this->notes,
                'stock_qty' => (int) $this->stock_qty,
            ]);
            flash()->success($this->editId ? __('Product updated.') : __('Product created.'));
            $this->showProductModal = false;
            $this->resetProductForm();
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function openMove(int $id): void
    {
        $this->moveProductId = $id;
        $this->move_type = 'in';
        $this->move_qty = '1';
        $this->move_reference = '';
        $this->move_notes = '';
        $this->showMoveModal = true;
    }

    public function saveMove(): void
    {
        $this->validate([
            'moveProductId' => 'required|integer',
            'move_type' => 'required|in:in,out,adjust',
            'move_qty' => 'required|integer|min:1',
            'move_reference' => 'nullable|string|max:120',
            'move_notes' => 'nullable|string|max:2000',
        ]);

        try {
            app(InventoryHubService::class)->moveStock(
                (int) $this->moveProductId,
                $this->move_type,
                (int) $this->move_qty,
                [
                    'reference' => $this->move_reference,
                    'notes' => $this->move_notes,
                ]
            );
            flash()->success(__('Stock movement saved.'));
            $this->showMoveModal = false;
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function toggleActive(int $id): void
    {
        app(InventoryHubService::class)->toggleActive($id);
        flash()->success(__('Product status updated.'));
    }

    public function refresh(): void
    {
        flash()->success(__('Inventory refreshed.'));
    }

    private function resetProductForm(): void
    {
        $this->sku = '';
        $this->name = '';
        $this->category = 'onu';
        $this->unit = 'pcs';
        $this->stock_qty = '0';
        $this->reorder_level = '0';
        $this->cost_price = '0';
        $this->sell_price = '0';
        $this->is_active = true;
        $this->notes = '';
    }

    public function render()
    {
        $data = app(InventoryHubService::class)->payload($this->search, $this->filter);

        return view('livewire.inventory-hub', $data)->layout('layouts.app');
    }
}
