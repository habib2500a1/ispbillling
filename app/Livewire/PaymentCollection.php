<?php

namespace App\Livewire;

use App\Http\Controllers\CustomersController;
use App\Http\Controllers\SMSController;
use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentCollection extends Component
{
    use WithPagination;

    public $user_list;

    public $customer_list = '';

    public ?CustomersInfo $info_data = null;

    public $collectionSummary = [];

    public $highlightedIndex = 0;

    public $customers = [];

    // In your Livewire component
    public $total_amount = 0;

    public $paid_amount;

    public $due_amount = '';

    public $expire_date = '';

    public $advance_paid = 0;

    public $apply_discount = 0;

    public $apply_advance = 0;

    public function mount()
    {
        if (! hasAccess(['Super Admin'], ['payment-collection'])) {
            abort(403, 'Unauthorized action.');
        }

        $customerId = request()->query('customer');
        if ($customerId) {
            try {
                $this->selectCustomer($customerId);
            } catch (\Throwable) {
                // ignore invalid encrypted id
            }
        }

        return true;
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
        if ($this->customer_list) {
            // Fetch customers dynamically based on the search term
            $this->customers = $this->resellerScope()
                ->search($this->customer_list)
                ->leftJoin('p_p_p_secrets', 'p_p_p_secrets.id', '=', 'customers_infos.ppp_user_id')
                ->with('customerAddress')
                ->select('customers_infos.id', 'customers_infos.customer_unique_id', 'customers_infos.customer_name', 'customers_infos.email', 'customers_infos.mobile', 'p_p_p_secrets.username as username')
                ->take(10)
                ->get();
        } else {
            $this->customers = [];
        }

        // Reset highlighted index whenever the list updates
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

    public function updatedApplyDiscount(): void
    {
        $this->calculatePayment();
    }

    public function updatedApplyAdvance(): void
    {
        $this->calculatePayment();
    }

    public function updatedPaidAmount(): void
    {
        $this->calculatePayment();
    }

    public function calculatePayment()
    {
        if ($this->info_data) {
            $payable = $this->payableDue();
            $this->total_amount = $payable;
            $this->due_amount = $payable - (float) ($this->paid_amount ?: 0);
        }

        if ($this->paid_amount >= 0 && $this->info_data) {
            $this->advance_paid = (float) ($this->paid_amount ?: 0) - (float) $this->total_amount;
            $baseExpire = $this->info_data->billing->auto_disable_date
                ? Carbon::parse($this->info_data->billing->auto_disable_date)
                : now();
            $rent = (int) ($this->info_data->billing->monthly_rent ?: 1);
            $disableMonths = (int) ($this->info_data->billing->auto_disable_month ?: 1);

            if ($this->due_amount > 0) {
                $extra_month = floor(((int) $this->due_amount) / $rent);

                if ($extra_month < $disableMonths) {
                    $this->expire_date = $baseExpire->copy()->month(now()->month)->year(now()->year)
                        ->subMonths($extra_month)
                        ->format('Y-m-d');
                } else {
                    $this->expire_date = $baseExpire->copy()->month(now()->month)->year(now()->year)
                        ->subMonths($disableMonths)
                        ->addMonths(1)
                        ->format('Y-m-d');
                }
            } elseif ($this->advance_paid > 0) {
                $extra_month = $rent <= 0 ? 1 : 1 + floor(((int) $this->advance_paid) / $rent);

                $this->expire_date = $baseExpire->copy()->month(now()->month)->year(now()->year)
                    ->addMonths($extra_month)
                    ->format('Y-m-d');
            } else {
                $this->expire_date = $baseExpire->copy()->month(now()->month)->year(now()->year)
                    ->addMonths(1)
                    ->format('Y-m-d');
            }
        } else {
            $this->expire_date = '';
        }
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
                // 'collectionSummary' => function ($query) {
                //     $query->whereMonth('collection_date', Carbon::now()->month)
                //         ->whereYear('collection_date', Carbon::now()->year);
                // }
            ])
            ->first();
        $this->collectionSummary = CollectionSummary::where('customer_collection_unique_id', $customer_id)
            ->whereMonth('collection_date', Carbon::now()->month)
            ->whereYear('collection_date', Carbon::now()->year)
            ->get();
        $this->paid_amount = '';
        $this->apply_discount = 0;
        $this->apply_advance = 0;
        $this->total_amount = $this->info_data->billing->due_amount;
        $this->due_amount = (float) $this->total_amount;
    }

    private function payableDue(): float
    {
        $base = (float) ($this->info_data->billing->due_amount ?? 0);
        $discount = max(0, (float) ($this->apply_discount ?: 0));
        $advance = max(0, (float) ($this->apply_advance ?: 0));

        return max(0, $base - $discount - $advance);
    }

    public function savePayment()
    {
        if ($this->info_data->status != 'active') {
            sweetalert()->showDenyButton()->info('Are you sure you want to Enable this customer?');
        }
        if ($this->info_data->status == 'active') {
            $this->paymentSubmit();
        }
    }

    #[On('sweetalert:confirmed')]
    public function onConfirmed(array $payload): void
    {
        $customerUniqueId = is_array($this->info_data)
            ? ($this->info_data['customer_unique_id'] ?? null)
            : ($this->info_data->customer_unique_id ?? null);

        if ($customerUniqueId) {
            $customer = CustomersInfo::where('customer_unique_id', $customerUniqueId)->first();
            if ($customer) {
                $customer->update([
                    'status' => 'active',
                ]);
                $this->info_data = $customer;
            }

            $customerEnable = new CustomersController;
            $customerEnable->customerEnable(encrypt($customerUniqueId));
        }
        $this->paymentSubmit();
    }

    #[On('sweetalert:denied')]
    public function onDeny(array $payload): void
    {
        $this->paymentSubmit();
    }

    public function paymentSubmit()
    {
        $paid = (float) ($this->paid_amount ?: 0);
        $discount = max(0, (float) ($this->apply_discount ?: 0));
        $advance = max(0, (float) ($this->apply_advance ?: 0));

        if ($paid <= 0 && $discount <= 0 && $advance <= 0) {
            flash()->error(__('Enter a pay amount, discount, or advance.'));

            return;
        }

        $this->calculatePayment();

        DB::beginTransaction();
        try {
            $payload = [
                'customer_collection_unique_id' => $this->info_data->customer_unique_id,
                'collection_date' => Carbon::now(),
                'collection_amount' => $paid,
                'collected_by' => auth()->user()->email,
                'payment_status' => 'paid',
                'invoice_no' => CollectionSummary::nextInvoiceNo(),
                'bill_month' => Carbon::now()->format('F Y'),
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('collection_summaries', 'discount_amount')) {
                $payload['discount_amount'] = $discount;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('collection_summaries', 'advance_amount')) {
                $payload['advance_amount'] = $advance;
            }
            CollectionSummary::create($payload);

            $remainingDue = (float) $this->due_amount;
            $advanceBump = $remainingDue < 0 ? abs($remainingDue) : 0;
            $billing = $this->info_data->billing;

            BillingInfo::where('customer_bill_unique_id', $this->info_data->customer_unique_id)
                ->update([
                    'paid_amount' => $paid + (float) $billing->paid_amount,
                    'paid_date' => Carbon::now(),
                    'auto_disable_date' => $this->expire_date,
                    'extra_date' => null,
                    'due_amount' => max(0, $remainingDue),
                    'total_amount' => max(0, (float) $billing->total_amount - $discount - $advance),
                    'discount' => (float) $billing->discount + $discount,
                    'advance' => (float) $billing->advance + $advance + $advanceBump,
                ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            sweetalert()->error($th->getMessage(), ['title' => 'Error']);
            return;
        }

        try {
            // Fire reseller commission event
            event(new \App\Events\PackagePurchased($this->info_data, (float) $this->paid_amount));

            flash()->success('Payment added successfully.');
            $data = [
                'recipient' => $this->info_data->mobile,
                'customer_id' => $this->info_data->customer_unique_id,
                'customer_name' => $this->info_data->customer_name,
                'collection_amount' => $this->paid_amount,
                'ip_or_user_name' => $this->info_data->pppUser->username ?? '',
                'due_amount' => $this->due_amount,
                'company_name' => siteUrlSettings('site_name'),
            ];

            // Call the SMSController's method
            $response = app(SMSController::class)->paymentCollectionSMS($data);
            if ($response && $response->isSuccessful()) {
                flash()->success($response->getMessage());
            } else {
                flash()->error($response ? $response->getMessage() : 'Failed to send SMS notification.');
            }
        } catch (\Throwable $th) {
            sweetalert()->error($th->getMessage(), ['title' => 'Error']);
        } finally {
            $this->reset();
            $this->dispatch('focusInput');
        }
    }

    public function render()
    {
        return view('livewire.payment-collection')->layout('layouts.app');
    }
}
