<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCollectionPaymentService;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Reseller\ResellerIntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'paymentMethods' => \App\Support\ResellerCollectionPaymentMethod::options(),
            'personalMfs' => ResellerIntegrationSettings::canManage($reseller)
                ? ResellerIntegrationSettings::personalPaymentSummary($reseller)
                : null,
        ]);
    }

    public function store(Request $request, Customer $customer, ResellerCollectionPaymentService $payments, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', Rule::in(\App\Support\ResellerCollectionPaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:500'],
            'invoice_id' => ['nullable', 'integer'],
        ]);

        $result = $payments->collect($reseller, $customer, $validated);
        app(\App\Services\Resellers\ResellerPortalActivityLogger::class)->log(
            $reseller,
            'payment.collect',
            $result['payment'],
            ['amount' => $validated['amount'], 'method' => $validated['method'] ?? 'cash'],
        );

        app(\App\Services\Resellers\ResellerPortalNotifier::class)->paymentReceived(
            $reseller,
            $customer->customer_code,
            (float) $validated['amount'],
            $result['payment']->id,
        );

        $payment = $result['payment'];

        return redirect()
            ->route('reseller.payments.receipt', $payment)
            ->with('status', ($result['message'] ?? 'Payment recorded.').' Receipt: '.$payment->receipt_number);
    }
}
