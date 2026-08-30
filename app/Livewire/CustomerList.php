<?php

namespace App\Livewire;

use App\Http\Controllers\MikrotikController;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\PaymentSummary;
use App\Models\PPPSecrets;
use App\Models\Reseller;
use App\Models\RouterList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Livewire\Attributes\On;
use Livewire\Component;
use Yajra\DataTables\Facades\DataTables;

class CustomerList extends Component
{
    public $editingCustomerId = null;

    public $editingBillId = null;

    public $routers = [];

    public $selectedRouter = '';

    public $monthly_rent = 0;

    public $additional_charge = 0;

    public $discount = 0;

    public $advance = 0;

    public $vat = 0;

    public $previous_due = 0;

    public $bill_paid_amount = 0;

    public $auto_disable = false;

    public $sub_total_amount = 0;

    public $total_amount = 0;

    public $bill_due_amount = 0;

    public $bill_customer_name;

    public $bill_customer_unique_id;

    public $bill_username;

    public $bill_auto_disable_date;

    public function render()
    {
        if (! hasAccess(['Super Admin'], ['all-customer'])) {
            abort(403, 'Unauthorized action.');
        }

        $this->routers = RouterList::all();
        $resellers = Reseller::with('user')->get();

        return view('livewire.customer-list', compact('resellers'))->layout('layouts.app');
    }

    public function getData(Request $request)
    {
        if (! hasAccess(['Super Admin'], ['all-customer'])) {
            abort(403, 'Unauthorized action.');
        }

        $statusFilter = ['pending', 'disable', 'free', 'inactive'];

        $data = CustomersInfo::query()
            ->with(['billing', 'pppUser', 'customerAddress', 'official', 'package', 'reseller'])
            ->select('customers_infos.*');

        // Router Filter
        if ($request->router_name) {
            $data->whereHas('pppUser', function ($q) use ($request) {
                $q->where('p_p_p_secrets.router_name', $request->router_name);
            });
        }

        // Filter logic
        switch ($request->filter) {
            case 'all':
                break;

            case 'all_active':
                $data->whereNotIn('status', $statusFilter);
                break;

            case 'without_collection':
                $data->whereHas('billing', function ($q) {
                    $q->where('paid_amount', 0);
                })->whereNotIn('status', $statusFilter);
                break;

            case 'collection':
                $data->whereHas('billing', function ($q) {
                    $q->where('paid_amount', '>', 0);
                })->whereNotIn('status', $statusFilter);
                break;

            case 'reseller':
                if ($request->reseller_id) {
                    $data->where('reseller_id', $request->reseller_id);
                } else {
                    $data->whereNotNull('reseller_id');
                }
                break;

            case 'active':
                $data->where('status', 'active');
                break;

            case 'pending':
            case 'disable':
            case 'free':
            case 'inactive':
            case 'vip':
                if ($request->filter === 'vip') {
                    $data->whereHas('official', fn ($q) => $q->where('customer_type', 'vip'));
                } elseif ($request->filter === 'inactive') {
                    $data->whereIn('status', ['inactive', 'disable']);
                } else {
                    $data->where('status', $request->filter);
                }
                break;

            case 'corporate':
                $data->whereHas('official', fn ($q) => $q->where('client_type', 'Corporate'));
                break;

            case 'expired':
                $data->whereHas('billing', function ($q) {
                    $q->whereDate('auto_disable_date', '<', Carbon::today())
                        ->where('due_amount', '>', 0)
                        ->where(function ($hold) {
                            $hold->whereNull('extra_date')
                                ->orWhereDate('extra_date', '<', Carbon::today());
                        });
                })->whereNotIn('status', ['free']);
                break;

            case 'expired_today':
                $data->whereHas('billing', fn ($q) => $q->whereDate('auto_disable_date', Carbon::today()));
                break;

            case 'inactive_due':
                $data->whereIn('status', ['inactive', 'disable'])
                    ->whereHas('billing', fn ($q) => $q->where('due_amount', '>', 0));
                break;

            case 'joined_month':
                $data->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
                break;

            case 'joined_today':
                $data->whereDate('created_at', Carbon::today());
                break;

            case 'inactive_today':
                $data->whereIn('status', ['inactive', 'disable'])
                    ->whereDate('updated_at', Carbon::today());
                break;

            case 'online':
                $data->whereHas('pppUser', fn ($q) => $q->whereNotNull('uptime')->where('status', '!=', 'removed'));
                break;

            case 'offline':
                $data->whereHas('pppUser', fn ($q) => $q->whereNull('uptime')->where('status', '!=', 'removed'));
                break;

            default:
                $data->whereNotIn('status', $statusFilter);
        }

        $search = trim((string) data_get($request->input('search'), 'value', $request->input('q', '')));

        return DataTables::eloquent($data)
            ->filter(function ($query) use ($search) {
                if ($search !== '') {
                    $query->search($search);
                }
            }, true)
            ->addIndexColumn()
            ->addColumn('customer_identity', function ($row) {
                $resellerBadge = $row->reseller_id && $row->reseller
                    ? ' <span class="badge ms-1" style="background-color: rgba(111, 66, 193, 0.1); color: rgb(111, 66, 193); border: 1px solid rgba(111, 66, 193, 0.25); font-size: 0.75rem;"><i class="bi bi-person-badge me-1"></i>'.$row->reseller->name.'</span>'
                    : '';

                // Round avatar: photo if exists, else coloured initials
                $initials = mb_strtoupper(mb_substr($row->customer_name ?? '', 0, 1, 'UTF-8'), 'UTF-8');
                $colors = ['#1e3a5f', '#2c5282', '#3d5a80', '#4a6fa5'];
                $bgColor = $colors[abs(crc32((string) $row->customer_unique_id)) % count($colors)];

                if (! empty($row->photo_url)) {
                    $avatar = '<img src="'.asset('storage/'.$row->photo_url).'" '
                        .'alt="'.e($row->customer_name).'" '
                        .'class="avatar avatar-2xl rounded-circle">';
                } else {
                    $avatar = '<div class="avatar avatar-2xl rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="background:'.$bgColor.';font-size:15px;">'
                        .$initials.'</div>';
                }

                $vipBadge = ($row->official?->customer_type === 'vip')
                    ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:.7rem;"><i class="bi bi-star-fill"></i> VIP</span>'
                    : '';
                $corpBadge = (strtolower((string) ($row->official?->client_type ?? '')) === 'corporate')
                    ? ' <span class="badge bg-info text-dark ms-1" style="font-size:.7rem;"><i class="bi bi-building"></i></span>'
                    : '';
                $viewUrl = route('customers.show', encrypt($row->customer_unique_id));

                return '<div class="d-flex align-items-center gap-2">'.
                    $avatar.
                    '<div>'.
                    '<div class="fw-bold text-dark"><a href="'.$viewUrl.'" class="text-dark text-decoration-none">'.$row->customer_name.'</a>'.
                    (! empty($row->contact_person && $row->contact_person != '-') ? '<small class="text-muted"> ('.$row->contact_person.')</small>' : '').
                    $resellerBadge.$vipBadge.$corpBadge.
                    '</div>'.

                    '<div class="small">
                        <span class="badge bg-secondary bg-opacity-10 text-secondary pe-2">'.$row->customer_unique_id.'</span> '.

                        (! empty($row->mobile)
                            ? '<span class="text-muted"><i class="bi bi-telephone text-success"></i> '.e($row->mobile).'</span>'.whatsapp_button($row->mobile).' '
                            : '').

                        (! empty($row->contact_email)
                            ? '<span class="text-muted"><i class="bi bi-envelope text-success"></i> '.$row->contact_email.'</span>'
                            : '').

                        ($row->joinDate()
                            ? ' <span class="text-muted"><i class="bi bi-calendar-check text-primary"></i> '.$row->joinDateLabel('d-M-Y').'</span>'
                            : '').

                    '</div>'.
                    '</div></div>';
            })
            ->addColumn('customers_address', function ($row) {
                $formattedAddresses = [];
                foreach ($row->customerAddress as $address) {
                    $parts = array_filter([$address->input_type_text, $address->input_type_dropdown, $address->input_type_textarea]);
                    $formattedAddresses[] = implode(', ', $parts);
                }

                return implode(', ', $formattedAddresses);
            })
            ->addColumn('billing_breakdown', function ($row) {
                $rent = number_format($row->billing?->monthly_rent ?? 0, 2);
                $p_due = number_format($row->billing?->previous_due ?? 0, 2);
                $a_charge = number_format($row->billing?->additional_charge ?? 0, 2);
                $vat = $row->billing?->vat ?? 0;
                $disc = number_format($row->billing?->discount ?? 0, 2);
                $adv = number_format($row->billing?->advance ?? 0, 2);

                return '<div class="small text-muted" style="font-size: 0.7rem; line-height: 1.4;">'.
                       '<div><i class="bi bi-calendar3 me-1"></i>Rent: <span class="text-dark fw-bold">'.$rent.',</span></div>'.
                       '<div><i class="bi bi-exclamation-triangle me-1"></i>P.Due: <span class="text-dark fw-bold">'.$p_due.',</span></div>'.
                       '<div><i class="bi bi-plus-circle me-1"></i>Add: <span class="text-dark fw-bold">'.$a_charge.'</span> | <i class="bi bi-percent me-1"></i>Vat: <span class="text-dark fw-bold">'.$vat.',</span></div>'.
                       '<div><i class="bi bi-tag me-1"></i>Disc: <span class="text-danger fw-bold">'.$disc.'</span> | <i class="bi bi-wallet-fill me-1"></i>Adv: <span class="text-success fw-bold">'.$adv.'</span></div>'.
                       '</div>';
            })
            ->addColumn('connection_details', function ($row) {
                $user = $row->pppUser->username ?? '—';
                $router = $row->pppUser->router_name ?? '—';
                $pkg = $row->package->package ?? '—';
                $price = $row->package->price ?? null;
                $priceHtml = $price !== null && $price !== '' ? ' · '.e((string) $price) : '';

                return '<div class="fw-semibold text-dark">'.e($user).'</div>'
                    .'<div class="small text-muted">'.e($router).' · '.e($pkg).$priceHtml.'</div>';
            })
            ->addColumn('billing_summary', function ($row) {
                $bill = number_format($row->billing?->total_amount ?? 0, 2);
                $paid = number_format($row->billing?->paid_amount ?? 0, 2);
                $dueRaw = (float) ($row->billing?->due_amount ?? 0);
                $due = number_format(max(0, $dueRaw), 2);

                return '<div class="billing-card small">'.
                       '<div class="d-flex justify-content-between"><span>Bill:</span> <span class="fw-bold text-primary">'.$bill.'</span></div>'.
                       '<div class="d-flex justify-content-between"><span>Paid:</span> <span class="fw-bold text-success">'.$paid.'</span></div>'.
                       '<hr class="my-1">'.
                       '<div class="d-flex justify-content-between"><span>Due:</span> <span class="fw-bold text-danger">'.$due.'</span></div>'.
                       '</div>';
            })
            ->addColumn('disable_details', function ($row) {
                $statusClass = $row->billing?->auto_disable == 1 ? 'bg-danger text-white' : 'bg-light text-muted';
                $disableDate = $row->billing?->auto_disable_date ? Carbon::parse($row->billing->auto_disable_date)->format('d-M-Y') : 'N/A';
                $tempHtml = '';
                if ($row->billing?->isTemporarilyExtended()) {
                    $tempHtml = '<div class="text-warning fw-bold" style="font-size: 0.75rem"><i class="bi bi-hourglass-split me-1"></i>Temp: '.Carbon::parse($row->billing->extra_date)->format('d-M-Y').'</div>';
                }

                return '<div class="small fw-bold mb-1">Count: '.$row->disable_count.'</div>'.
                       '<span class="badge badge-soft '.$statusClass.' mb-1">Auto: '.($row->billing?->auto_disable == 1 ? 'Yes' : 'No').'</span>'.
                       '<div class="text-primary fw-bold" style="font-size: 0.75rem"><i class="bi bi-calendar-x me-1"></i>'.$disableDate.'</div>'.
                       $tempHtml.
                       '<div class="text-muted" style="font-size: 0.7rem">Ext: '.($row->billing?->auto_disable_month ?? 0).' Mon</div>';
            })
            ->addColumn('action', function ($row) {
                $enable_btn = '<button onclick="confirmEnableCustomer(\''.encrypt($row->customer_unique_id).'\')" class="btn btn-success"><i class="bi bi-power"></i></button>';
                $delete_btn = '<button onclick="confirmDeleteCustomer(\''.encrypt($row->customer_unique_id).'\')" class="btn btn-danger"><i class="bi bi-trash"></i></button>';
                $view_btn = '<a href="'.route('customers.show', encrypt($row->customer_unique_id)).'" class="btn btn-success" title="View"><i class="bi bi-eye"></i></a>';
                $customers_edit_btn = '<button onclick="Livewire.dispatch(\'open-edit-customer\', { id: \''.encrypt($row->customer_unique_id).'\' })" class="edit btn btn-primary"><i class="bi bi-pencil-square"></i></button>';
                $bill_edit_btn = '<button onclick="Livewire.dispatch(\'open-bill-modal\', { id: \''.encrypt($row->customer_unique_id).'\' })" class="bill btn btn-info"><i class="bi bi-journal-arrow-up"></i></button>';

                $btns = '<div class="action-btns d-flex justify-content-center">'.$view_btn;

                if ($row->status === 'pending') {
                    if (hasAccess(['Super Admin'], ['edit-customer', 'enable-pending-customer', 'delete-customer'])) {
                        $btns .= $customers_edit_btn.$enable_btn.$delete_btn;
                    } elseif (hasAccess(['Super Admin'], ['edit-customer'])) {
                        $btns .= $customers_edit_btn;
                    } elseif (hasAccess(['Super Admin'], ['enable-pending-customer'])) {
                        $btns .= $enable_btn;
                    } elseif (hasAccess(['Super Admin'], ['delete-customer'])) {
                        $btns .= $delete_btn;
                    }
                } elseif ($row->status === 'disable') {
                    if (hasAccess(['Super Admin'], ['edit-customer', 'enable-pending-customer', 'delete-customer'])) {
                        $btns .= $customers_edit_btn.$enable_btn.$delete_btn;
                    } elseif (hasAccess(['Super Admin'], ['edit-customer'])) {
                        $btns .= $customers_edit_btn;
                    } elseif (hasAccess(['Super Admin'], ['enable-pending-customer'])) {
                        $btns .= $enable_btn;
                    } elseif (hasAccess(['Super Admin'], ['delete-customer'])) {
                        $btns .= $delete_btn;
                    }
                } elseif ($row->status === 'inactive') {
                    if (hasAccess(['Super Admin'], ['edit-customer', 'delete-customer'])) {
                        $btns .= $customers_edit_btn.$delete_btn;
                    } elseif (hasAccess(['Super Admin'], ['edit-customer'])) {
                        $btns .= $customers_edit_btn;
                    } elseif (hasAccess(['Super Admin'], ['delete-customer'])) {
                        $btns .= $delete_btn;
                    }
                } else {
                    if (hasAccess(['Super Admin'], ['edit-customer', 'update-bill'])) {
                        $btns .= $customers_edit_btn.$bill_edit_btn;
                    } elseif (hasAccess(['Super Admin'], ['edit-customer'])) {
                        $btns .= $customers_edit_btn;
                    } elseif (hasAccess(['Super Admin'], ['update-bill'])) {
                        $btns .= $bill_edit_btn;
                    }
                }

                if ($row->pppUser && !empty($row->pppUser->router_name) && hasAccess(['Super Admin'], ['push-customers'])) {
                    $push_btn = '<button onclick="confirmPushCustomer(\''.encrypt($row->customer_unique_id).'\')" class="btn btn-warning text-white ms-1" title="Push to MikroTik"><i class="bi bi-cloud-arrow-up"></i></button>';
                    $btns .= $push_btn;
                }

                if ($row->pppUser && hasAccess(['Super Admin'], ['all-customer', 'edit-customer'])) {
                    $portal_btn = '<a href="'.route('staff.subscribers.portal-login', $row->id).'" target="_blank" rel="noopener" class="btn btn-info text-white ms-1" title="'.e(__('Portal Login')).'"><i class="bi bi-box-arrow-in-right"></i></a>';
                    $btns .= $portal_btn;
                }

                return $btns.'</div>';
            })
            ->editColumn('billing.due_amount', fn ($row) => max(0, (float) ($row->billing?->due_amount ?? 0)))
            ->rawColumns(['customer_identity', 'customers_address', 'billing_breakdown', 'connection_details', 'billing_summary', 'action', 'disable_details'])
            ->make(true);
    }

    public function show(string $id)
    {
        $unique_id = decrypt($id);
        $data = CustomersInfo::where('customer_unique_id', $unique_id)
            ->join('billing_infos', 'customers_infos.customer_unique_id', '=', 'billing_infos.customer_bill_unique_id')
            ->leftJoin('p_p_p_secrets', 'p_p_p_secrets.id', '=', 'customers_infos.ppp_user_id')
            ->select('customers_infos.customer_unique_id', 'customers_infos.customer_name', 'billing_infos.*', 'p_p_p_secrets.username as username')
            ->first();

        return response()->json($data);
    }

    public function edit(string $id)
    {
        return view('edit-customer', [
            'customerId' => $id,
        ]);
    }

    #[On('enable-customer')]
    public function enableCustomer($id): void
    {
        // For array wrappers
        $id = is_array($id) ? $id['id'] ?? $id : $id;

        if (! hasAccess(['Super Admin'], ['enable-pending-customer'])) {
            flash()->addError('Unauthorized action.');
            $this->dispatch('customer-action-done');

            return;
        }

        $unique_id = decrypt($id);
        $bill = BillingInfo::where('customer_bill_unique_id', $unique_id)->first();

        if (! $bill) {
            flash()->addError('Billing Information not found.');
            $this->dispatch('customer-action-done');

            return;
        }

        try {
            \DB::beginTransaction();

            $summaryExists = PaymentSummary::where('customer_payment_unique_id', $unique_id)
                ->where('summary_date', Carbon::now()->firstOfMonth()->format('Y-m-d'))
                ->exists();

            if (! $summaryExists) {
                PaymentSummary::create([
                    'customer_payment_unique_id' => $unique_id,
                    'summary_date' => Carbon::now()->firstOfMonth()->format('Y-m-d'),
                    'monthly_rent' => $bill->monthly_rent,
                    'additional_charge' => $bill->additional_charge,
                    'vat' => $bill->vat,
                    'previous_due' => $bill->previous_due,
                    'advance' => $bill->advance,
                    'discount' => $bill->discount,
                ]);
            }

            $customer = CustomersInfo::where('customer_unique_id', $unique_id)->with('pppUser')->first();

            if (! $customer) {
                \DB::rollBack();
                flash()->addError('Customer not found.');
                $this->dispatch('customer-action-done');

                return;
            }

            $customer->status = 'active';
            $customer->save();

            if ($bill->auto_disable_date) {
                $autoDisableDate = Carbon::parse($bill->auto_disable_date)->startOfDay();
                $autoDisableMonth = $bill->auto_disable_month;
                $disableDate = $autoDisableDate->copy()->addMonths($autoDisableMonth);

                if ($disableDate->lte(today())) {
                    while ($disableDate->lte(today())) {
                        $disableDate->addMonth();
                    }
                    $bill->auto_disable_date = $disableDate->copy()->subMonths($autoDisableMonth)->toDateString();
                    $bill->save();
                }
            }

            if ($customer->pppUser) {
                PPPSecrets::where('id', $customer->ppp_user_id)->update(['status' => 'active']);

                if (! empty($customer->pppUser->router_name)) {
                    app(MikrotikController::class)->enablePPPSecret(
                        $unique_id,
                        $customer->pppUser->router_name,
                        $customer->pppUser->username
                    );

                    app(MikrotikController::class)->updatePPPSecret(
                        $customer->pppUser->router_name,
                        $customer->pppUser->username,
                        'profile',
                        $customer->pppUser->profile
                    );

                    // Remove active PPP session via pooled/cached controller (auto-invalidates cache)
                    try {
                        app(MikrotikController::class)->singleWrite(
                            $customer->pppUser->router_name,
                            '/ppp active remove [find name="'.$customer->pppUser->username.'"]'
                        );
                    } catch (\Exception $e) {
                        // Active session may not exist — not a critical error
                        \Log::debug('enableCustomer: active session removal skipped: '.$e->getMessage());
                    }
                }
            }

            \DB::commit();
            flash()->addSuccess($customer->pppUser ? 'Customer enabled successfully and PPP secret activated.' : 'Customer enabled successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Failed to enable customer '.$unique_id.': '.$e->getMessage());
            flash()->addError('Failed to enable customer on router: '.$e->getMessage());
        }

        $this->dispatch('customer-action-done');
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['monthly_rent', 'additional_charge', 'discount', 'advance', 'vat', 'previous_due'])) {
            $this->calculateBill();
        }
    }

    public function calculateBill()
    {
        $monthlyRent = (float) ($this->monthly_rent ?: 0);
        $previousDue = (float) ($this->previous_due ?: 0);
        $additionalCharge = (float) ($this->additional_charge ?: 0);
        $discount = (float) ($this->discount ?: 0);
        $advance = (float) ($this->advance ?: 0);
        $vat = (float) ($this->vat ?: 0);
        $paid = (float) ($this->bill_paid_amount ?: 0);

        $subtotal = $monthlyRent + $previousDue + $additionalCharge;
        $vatAmount = ($vat / 100) * $subtotal;
        $this->sub_total_amount = round($subtotal + $vatAmount, 2);
        $this->total_amount = round($this->sub_total_amount - ($discount + $advance), 2);
        $this->bill_due_amount = round($this->total_amount - $paid, 2);
    }

    #[On('open-bill-modal')]
    public function openBillModal($id)
    {
        $id = is_array($id) ? $id['id'] ?? $id : $id;
        $this->editingBillId = $id;

        $unique_id = decrypt($id);
        $customer = CustomersInfo::where('customer_unique_id', $unique_id)
            ->with(['billing', 'pppUser'])
            ->first();

        if ($customer) {
            $this->bill_customer_name = $customer->customer_name;
            $this->bill_customer_unique_id = $customer->customer_unique_id;
            $this->bill_username = $customer->pppUser?->username ?? '';
            $this->bill_auto_disable_date = $customer->billing?->auto_disable_date ?? '';

            $this->monthly_rent = $customer->billing?->monthly_rent ?? 0;
            $this->additional_charge = $customer->billing?->additional_charge ?? 0;
            $this->discount = $customer->billing?->discount ?? 0;
            $this->advance = $customer->billing?->advance ?? 0;
            $this->vat = $customer->billing?->vat ?? 0;
            $this->previous_due = $customer->billing?->previous_due ?? 0;
            $this->bill_paid_amount = $customer->billing?->paid_amount ?? 0;
            $this->auto_disable = (bool) ($customer->billing?->auto_disable == 1);

            $this->calculateBill();
        }
    }

    public function updateBill()
    {
        if (! hasAccess(['Super Admin'], ['update-bill'])) {
            flash()->addError('Unauthorized action.');

            return;
        }

        try {
            BillingInfo::where('customer_bill_unique_id', decrypt($this->editingBillId))->update([
                'monthly_rent' => $this->monthly_rent ?: 0,
                'additional_charge' => $this->additional_charge ?: 0,
                'discount' => $this->discount ?: 0,
                'advance' => $this->advance ?: 0,
                'vat' => $this->vat ?: 0,
                'total_amount' => $this->total_amount ?: 0,
                'due_amount' => $this->bill_due_amount ?: 0,
                'auto_disable' => $this->auto_disable ? 1 : 0,
            ]);

            flash()->success('Billing information updated successfully.');
            $this->closeBillModal();
        } catch (\Exception $e) {
            flash()->addError($e->getMessage());
        }
    }

    public function closeBillModal()
    {
        $this->editingBillId = null;
        $this->dispatch('customer-action-done');
    }

    #[On('delete-customer')]
    public function deleteCustomer($id): void
    {
        $id = is_array($id) ? $id['id'] ?? $id : $id;

        if (! hasAccess(['Super Admin'], ['delete-customer'])) {
            flash()->addError('Unauthorized action.');
            $this->dispatch('customer-action-done');

            return;
        }
        try {
            $decryptedId = decrypt($id);
            $customerDelete = CustomersInfo::where('customer_unique_id', $decryptedId)->with('pppUser')->first();

            if (! $customerDelete) {
                flash()->addError('Customer not found.');
                $this->dispatch('customer-action-done');

                return;
            }

            try {
                \DB::beginTransaction();

                $pppUser = $customerDelete->pppUser;
                if ($pppUser) {
                    if (! empty($pppUser->router_name)) {
                        app(MikrotikController::class)->removePPPSecret(
                            $decryptedId,
                            $pppUser->router_name,
                            $pppUser->username
                        );
                    }
                    $pppUser->delete();
                }

                $customerDelete->delete();

                \DB::commit();
                flash()->addSuccess('Customer deleted successfully.');
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error('Failed to delete customer '.$decryptedId.': '.$e->getMessage());
                flash()->addError('Failed to delete customer on router: '.$e->getMessage());
            }
        } catch (\Exception $e) {
            flash()->addError($e->getMessage());
        }

        $this->dispatch('customer-action-done');
    }

    #[On('open-edit-customer')]
    public function openEditCustomerModal($id)
    {
        $this->editingCustomerId = is_array($id) ? $id['id'] ?? $id : $id;
    }

    public function closeEditCustomerModal()
    {
        $this->editingCustomerId = null;
        $this->dispatch('customer-action-done');
    }

    #[On('push-customer')]
    public function pushCustomer($id): void
    {
        $id = is_array($id) ? $id['id'] ?? $id : $id;

        if (! hasAccess(['Super Admin'], ['edit-customer'])) {
            flash()->addError('Unauthorized action.');
            $this->dispatch('customer-action-done');

            return;
        }

        try {
            $unique_id = decrypt($id);
            $customer = CustomersInfo::where('customer_unique_id', $unique_id)->with('pppUser')->first();

            if (! $customer) {
                flash()->addError('Customer not found.');
                $this->dispatch('customer-action-done');

                return;
            }

            if (! $customer->pppUser) {
                flash()->addError('Customer does not have PPP/Mikrotik User details.');
                $this->dispatch('customer-action-done');

                return;
            }

            $this->syncCustomer($customer);

            flash()->addSuccess("Customer successfully pushed to MikroTik router.");
        } catch (\Exception $e) {
            \Log::error("Failed to push customer: " . $e->getMessage());
            flash()->addError("Failed to push to router: " . $e->getMessage());
        }

        $this->dispatch('customer-action-done');
    }

    #[On('push-all-customers')]
    public function pushAllCustomers(): void
    {
        if (! hasAccess(['Super Admin'], ['edit-customer'])) {
            flash()->addError('Unauthorized action.');
            $this->dispatch('customer-action-done');
            return;
        }

        try {
            $customers = CustomersInfo::whereHas('pppUser', function ($q) {
                $q->whereNotNull('router_name')->where('router_name', '!=', '');
            })->with('pppUser')->get();

            if ($customers->isEmpty()) {
                flash()->addError('No customers with router configuration found.');
                $this->dispatch('customer-action-done');
                return;
            }

            $success = 0; $failed = 0;
            foreach ($customers as $customer) {
                try {
                    $this->syncCustomer($customer);
                    $success++;
                } catch (\Exception $e) {
                    \Log::error("Failed bulk push for customer {$customer->customer_unique_id}: " . $e->getMessage());
                    $failed++;
                }
            }

            if ($failed > 0) {
                flash()->addWarning("Push completed with some issues. Success: {$success}, Failed: {$failed}");
            } else {
                flash()->addSuccess("Successfully pushed/synchronized {$success} customers to MikroTik.");
            }

        } catch (\Exception $e) {
            \Log::error("Failed bulk push to routers: " . $e->getMessage());
            flash()->addError("Failed to push all customers: " . $e->getMessage());
        }

        $this->dispatch('customer-action-done');
    }

    private function syncCustomer(CustomersInfo $customer): void
    {
        $user = $customer->pppUser;
        if (!$user || empty($user->router_name)) return;

        $router = $user->router_name;
        $name = $user->username;
        $qName = app(MikrotikController::class)->mtQuote($name);
        $ip = $user->ppp_remote_ip ?: $user->ip_address;
        $comment = $user->comment ?: '';

        if ($user->service === 'pppoe') {
            // Check if secret exists on router
            $check = app(MikrotikController::class)->singleRead($router, '/ppp/secret/print', "ppp secret print without-paging terse where name=$qName", ['name' => $name], false);
            $exists = is_array($check) && count($check) > 0;

            $pass = $user->password;
            $prof = $user->profile;
            $caller = $user->caller_id ?? '';

            if (!$exists) {
                $cmd = "/ppp secret add name=\"$name\" password=\"$pass\" service=\"pppoe\" profile=\"$prof\" comment=\"$comment\" caller-id=\"$caller\"" . (!empty($ip) ? " remote-address=\"$ip\"" : "") . " disabled=yes";
            } else {
                $cmd = "/ppp secret set [find name=$qName] password=\"$pass\" profile=\"$prof\" comment=\"$comment\" caller-id=\"$caller\"" . (!empty($ip) ? " remote-address=\"$ip\"" : "");
            }
            app(MikrotikController::class)->singleWrite($router, $cmd);

            // Restore status
            if ($customer->status === 'active') {
                app(MikrotikController::class)->enablePPPSecret($customer->customer_unique_id, $router, $name);
            } elseif ($customer->status === 'disable') {
                app(MikrotikController::class)->disablePPPSecret($customer->customer_unique_id, $router, $name);
            } else {
                app(MikrotikController::class)->singleWrite($router, "/ppp secret set [find name=$qName] disabled=yes");
            }
        } elseif ($user->service === 'static') {
            // Check if simple queue exists on router
            $check = app(MikrotikController::class)->singleRead($router, '/queue/simple/print', "queue simple print without-paging terse where name=$qName", ['name' => $name], false);
            $exists = is_array($check) && count($check) > 0;

            $limit = $user->bandwidth ?: '10M/10M';
            $interface = $user->profile ?: '';

            if (!$exists) {
                $cmd = "/queue simple add name=\"$name\" profile=\"$interface\" address=\"$ip\" max-limit=\"$limit\" comment=\"$comment\" disabled=yes";
            } else {
                $cmd = "/queue simple set [find name=$qName] profile=\"$interface\" address=\"$ip\" max-limit=\"$limit\" comment=\"$comment\"";
            }
            app(MikrotikController::class)->singleWrite($router, $cmd);

            // Restore status
            app(MikrotikController::class)->toggleSimpleQueue($router, $name, $customer->status === 'active');
        }
    }
}
