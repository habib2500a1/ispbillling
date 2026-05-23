<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCollectionPaymentService;
use App\Services\Resellers\ResellerCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerPaymentController extends Controller
{
    public function create(Customer $customer, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);
        $customer->load('package:id,name');

        return view('reseller.collect-payment', [
            'reseller' => $reseller,
            'customer' => $customer,
            'openDue' => $customer->openInvoiceBalance(),
        ]);
    }

    public function store(Request $request, Customer $customer, ResellerCollectionPaymentService $payments, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:500'],
            'invoice_id' => ['nullable', 'integer'],
        ]);

        $result = $payments->collect($reseller, $customer, $validated);

        return redirect()
            ->route('reseller.customers.show', $customer)
            ->with('status', $result['message'] ?? 'Payment recorded.');
    }
}
