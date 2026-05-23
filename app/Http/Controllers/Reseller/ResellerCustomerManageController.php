<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerService;
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

        return redirect()
            ->route('reseller.customers.show', $result['customer'])
            ->with('status', 'Subscriber created. '.$result['billing']['message']);
    }

    public function show(Customer $customer, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);
        $customer->load(['package:id,name', 'zone:id,name']);

        return view('reseller.customers-show', [
            'reseller' => $reseller,
            'customer' => $customer,
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
