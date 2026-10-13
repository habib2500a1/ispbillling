<?php

namespace App\Livewire\Admin;

use App\Models\BillingInfo;
use App\Models\BillingWaiver;
use App\Models\CustomersInfo;
use Illuminate\Support\Facades\DB;
use App\Services\Saas\SaasContext;
use Livewire\Component;
use Livewire\WithPagination;

class WaiverManagement extends Component
{
    use WithPagination;

    public string $search = '';

    public string $customerSearch = '';

    public string $customerUniqueId = '';

    public string $amount = '';

    public string $reason = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function selectCustomer(string $customerUniqueId): void
    {
        $this->authorizeAccess();
        $customer = CustomersInfo::query()
            ->where('customer_unique_id', $customerUniqueId)
            ->firstOrFail();

        $this->customerUniqueId = $customer->customer_unique_id;
        $this->customerSearch = trim($customer->customer_name.' · '.$customer->customer_unique_id);
    }

    public function clearCustomer(): void
    {
        $this->authorizeAccess();
        $this->customerUniqueId = '';
        $this->customerSearch = '';
    }

    public function applyWaiver(): void
    {
        $this->authorizeAccess();
        $validated = $this->validate([
            'customerUniqueId' => ['required', 'string', 'exists:customers_infos,customer_unique_id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $customer = CustomersInfo::query()
            ->where('customer_unique_id', $validated['customerUniqueId'])
            ->firstOrFail();

        $applied = DB::transaction(function () use ($customer, $validated): bool {
            $billing = BillingInfo::query()
                ->where('customer_bill_unique_id', $customer->customer_unique_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = round((float) $validated['amount'], 2);
            $due = max(0, (float) $billing->due_amount);

            if ($amount > $due) {
                $this->addError('amount', __('The waiver cannot exceed the customer\'s current outstanding due of ৳:due.', [
                    'due' => number_format($due, 2),
                ]));

                return false;
            }

            BillingWaiver::create([
                'saas_operator_id' => SaasContext::tenantId(),
                'customer_unique_id' => $customer->customer_unique_id,
                'amount' => $amount,
                'reason' => $validated['reason'],
                'waived_by' => auth()->id(),
            ]);

            $billing->due_amount = max(0, $due - $amount);
            $billing->total_amount = max(0, (float) $billing->total_amount - $amount);
            $billing->discount = (float) $billing->discount + $amount;
            $billing->save();

            return true;
        });

        if (! $applied) {
            return;
        }

        $this->reset(['amount', 'reason', 'customerUniqueId', 'customerSearch']);
        flash()->success(__('Bill waiver applied and recorded.'));
    }

    public function render()
    {
        $this->authorizeAccess();

        $customerMatches = collect();
        $term = trim($this->customerSearch);
        if ($term !== '' && $this->customerUniqueId === '') {
            $customerMatches = CustomersInfo::query()
                ->whereNull('deleted_at')
                ->where(function ($query) use ($term) {
                    $query->where('customer_unique_id', 'like', '%'.$term.'%')
                        ->orWhere('customer_name', 'like', '%'.$term.'%')
                        ->orWhere('mobile', 'like', '%'.$term.'%');
                })
                ->orderBy('customer_name')
                ->limit(8)
                ->get(['customer_unique_id', 'customer_name', 'mobile']);
        }

        $waivers = BillingWaiver::query()
            ->with(['customer', 'waivedBy'])
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($inner) use ($term) {
                    $inner->where('customer_unique_id', 'like', '%'.$term.'%')
                        ->orWhere('reason', 'like', '%'.$term.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer->where('customer_name', 'like', '%'.$term.'%'));
                });
            })
            ->latest()
            ->paginate(20);

        $summary = [
            'count' => BillingWaiver::count(),
            'amount' => (float) BillingWaiver::sum('amount'),
        ];

        return view('livewire.admin.waiver-management', compact('customerMatches', 'waivers', 'summary'))
            ->layout('layouts.app');
    }

    private function authorizeAccess(): void
    {
        if (! hasAccess(['Super Admin'], ['payment-collection', 'amount-collection', 'payment-collection-report'])) {
            abort(403, 'Unauthorized action.');
        }
    }
}
