<?php

namespace App\Livewire;

use App\Services\Inventory\InventorySaleService;
use Livewire\Component;

class InventorySales extends Component
{
    public string $filter = 'all';

    public bool $showModal = false;

    public string $channel = 'counter';

    public string $customerSearch = '';

    public ?string $customerUid = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $discount = '0';

    public string $payment_method = 'cash';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: string, unit_price: string}> */
    public array $lines = [];

    /** @var list<array{uid: string, label: string, mobile: ?string}> */
    public array $customerResults = [];

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['admin.expenses', 'olt-management', 'mikrotik-sync'])) {
            abort(403, 'Unauthorized action.');
        }
        $this->resetLines();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    public function openCreate(string $channel = 'counter'): void
    {
        $this->channel = array_key_exists($channel, \App\Models\InventorySale::CHANNELS) ? $channel : 'counter';
        $this->customerSearch = '';
        $this->customerUid = null;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->discount = '0';
        $this->payment_method = $this->channel === 'counter' ? 'cash' : 'n/a';
        $this->notes = '';
        $this->customerResults = [];
        $this->resetLines();
        $this->showModal = true;
    }

    public function updatedCustomerSearch(): void
    {
        $this->customerResults = app(InventorySaleService::class)->searchCustomers($this->customerSearch, 12);
    }

    public function selectCustomer(string $uid, string $label, ?string $mobile = null): void
    {
        $this->customerUid = $uid;
        $this->customerName = preg_replace('/\s*\(.*\)$/', '', $label) ?: $label;
        $this->customerPhone = $mobile ?? '';
        $this->customerSearch = $label;
        $this->customerResults = [];
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '1', 'unit_price' => '0'];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->resetLines();
        }
    }

    public function saveSale(): void
    {
        $this->validate([
            'channel' => 'required|in:counter,issue,field',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        try {
            $items = [];
            foreach ($this->lines as $line) {
                $items[] = [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => (float) ($line['unit_price'] ?: 0),
                ];
            }

            $sale = app(InventorySaleService::class)->record([
                'channel' => $this->channel,
                'customer_unique_id' => $this->customerUid,
                'customer_name' => $this->customerName ?: null,
                'customer_phone' => $this->customerPhone ?: null,
                'discount' => $this->discount,
                'payment_method' => $this->payment_method,
                'notes' => $this->notes,
                'items' => $items,
            ]);

            flash()->success(__('Saved :no — stock updated.', ['no' => $sale->sale_number]));
            $this->showModal = false;
        } catch (\Throwable $e) {
            flash()->error($e->getMessage());
        }
    }

    public function refresh(): void
    {
        flash()->success(__('Sales refreshed.'));
    }

    private function resetLines(): void
    {
        $this->lines = [['product_id' => '', 'quantity' => '1', 'unit_price' => '0']];
    }

    public function render()
    {
        $data = app(InventorySaleService::class)->payload($this->filter);

        return view('livewire.inventory-sales', $data)->layout('layouts.app');
    }
}
