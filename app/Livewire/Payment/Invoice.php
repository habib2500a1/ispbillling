<?php

namespace App\Livewire\Payment;

use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class Invoice extends Component
{
    use WithPagination;

    public $user_list;

    public $customer_list = '';

    public $info_data = [];

    public $collectionSummary = [];

    public $highlightedIndex = 0;

    public $customers = [];

    // In your Livewire component
    public $total_amount = 0;

    public $paid_amount;

    public $due_amount = '';

    public $expire_date = '';

    public $advance_paid = 0;

    public function mount()
    {
        if (! hasAccess(['Super Admin', 'Operator'], ['payment-collection-invoice', 'payment-collection', 'all-customer'])) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Returns a CustomersInfo query builder scoped to the reseller's
     * own customers when the logged-in user is a Reseller, otherwise
     * returns an unscoped query for admins.
     */
    private function resellerScope()
    {
        $user = auth()->user();

        if ($user->hasRole('Reseller') && $user->reseller) {
            return CustomersInfo::where('reseller_id', $user->reseller->id);
        }

        return CustomersInfo::query();
    }

    public function updatedCustomerList()
    {
        $term = trim($this->customer_list);
        if ($term === '' || strlen($term) < 2) {
            $this->customers = [];
            $this->highlightedIndex = 0;

            return;
        }

        $query = $this->resellerScope()
            ->leftJoin('p_p_p_secrets', 'p_p_p_secrets.id', '=', 'customers_infos.ppp_user_id')
            ->select(
                'customers_infos.id',
                'customers_infos.customer_unique_id',
                'customers_infos.customer_name',
                'customers_infos.email',
                'customers_infos.mobile',
                'p_p_p_secrets.username as username'
            );

        $exact = (clone $query)
            ->where(function ($q) use ($term) {
                $q->where('customers_infos.customer_unique_id', $term)
                    ->orWhere('p_p_p_secrets.username', $term);
            })
            ->take(10)
            ->get();

        $this->customers = $exact->isNotEmpty()
            ? $exact
            : $query->search($term)->take(10)->get();

        $this->highlightedIndex = 0;
    }

    public function incrementHighlight()
    {
        if ($this->highlightedIndex < count($this->customers) - 1) {
            $this->highlightedIndex++;
        }
    }

    public function decrementHighlight()
    {
        if ($this->highlightedIndex > 0) {
            $this->highlightedIndex--;
        }
    }

    public function selectHighlightedCustomer()
    {
        if (isset($this->customers[$this->highlightedIndex])) {
            $selectedCustomer = $this->customers[$this->highlightedIndex];
            $this->selectCustomer(encrypt($selectedCustomer->customer_unique_id));
        }
    }

    public function printPage()
    {
        $this->dispatch('triggerPrint');

    }

    public function selectCustomer($value)
    {
        // $this->paid_amount;
        $this->expire_date = '';
        $customer_id = decrypt($value);
        $this->customer_list = '';
        $this->customers = [];
        $this->info_data = $this->resellerScope()
            ->where('customer_unique_id', $customer_id)
            ->with([
                'customerAddress',
                'billing',
                'official',
                'pppUser',
                'onus',
                'package',
            ])
            ->first();
        $this->collectionSummary = CollectionSummary::where('customer_collection_unique_id', $customer_id)
            ->where('collection_date', '>=', Carbon::now()->startOfMonth())
            ->where('collection_date', '<', Carbon::now()->addMonth()->startOfMonth())
            ->get();
        $this->paid_amount = '';
        $this->total_amount = $this->info_data->billing->due_amount;
        $this->due_amount = (int) $this->total_amount - (int) $this->paid_amount;
    }

    public function render()
    {
        return view('livewire.payment.invoice')->layout('layouts.app');
    }
}
