<?php

namespace App\Livewire;

use App\Models\CustomersInfo;
use Livewire\Component;
use Livewire\WithPagination;

class BillingAdjustments extends Component
{
    use WithPagination;

    public string $type = 'discount';

    public string $q = '';

    public function mount(): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection', 'payment-collection-report', 'amount-collection'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->type = request()->routeIs('billing.advances') ? 'advance' : 'discount';
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $column = $this->type === 'advance' ? 'advance' : 'discount';
        $search = trim($this->q);

        $rows = CustomersInfo::query()
            ->whereNull('deleted_at')
            ->whereHas('billing', fn ($q) => $q->where($column, '>', 0))
            ->with(['billing', 'pppUser'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('customer_unique_id', 'like', '%'.$search.'%')
                        ->orWhere('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc(
                \App\Models\BillingInfo::query()
                    ->select($column)
                    ->whereColumn('customer_bill_unique_id', 'customers_infos.customer_unique_id')
                    ->limit(1)
            )
            ->paginate(25);

        $total = (float) \App\Models\BillingInfo::query()
            ->join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->sum('billing_infos.'.$column);

        return view('livewire.billing-adjustments', [
            'rows' => $rows,
            'total' => $total,
            'column' => $column,
        ])->layout('layouts.app');
    }
}
