<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerNetworkSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerCustomerManageController extends Controller
{
    public function create(ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();

        return view('reseller.customers-create', [
            'reseller' => $reseller,
            'options' => $customers->formOptions($reseller),
        ]);
    }

    public function store(Request $request, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $customers->create($reseller, $request);
        $message = 'Subscriber created. '.($result['billing']['message'] ?? '');

        if (! empty($result['billing']['payment'])) {
            return redirect()
                ->route('reseller.payments.receipt', $result['billing']['payment'])
                ->with('status', $message);
        }

        return redirect()
            ->route('reseller.customers.show', $result['customer'])
            ->with('status', $message);
    }

    public function show(Customer $customer, ResellerCustomerService $customers, ResellerNetworkSessionService $networkSessions): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);
        $customer->load(['package:id,name', 'zone:id,name', 'area:id,name']);

        $payments = $customer->payments()
            ->where('status', 'completed')
            ->latest('paid_at')
            ->limit(15)
            ->get();

        $invoices = $customer->invoices()
            ->latest('issue_date')
            ->limit(15)
            ->get();

        $networkSession = $networkSessions->liveDetail($customer);

        return view('reseller.customers-show', [
            'reseller' => $reseller,
            'customer' => $customer,
            'payments' => $payments,
            'invoices' => $invoices,
            'networkSession' => $networkSession,
        ]);
    }

    public function edit(Customer $customer, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        return view('reseller.customers-edit', [
            'reseller' => $reseller,
            'customer' => $customer,
            'options' => $customers->formOptions($reseller),
        ]);
    }

    public function update(Request $request, Customer $customer, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $customers->update($reseller, $customer, $request);

        return redirect()
            ->route('reseller.customers.show', $customer)
            ->with('status', 'Subscriber updated.');
    }
}
