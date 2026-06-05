<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Billing\OpenInvoiceResolver;
use App\Services\Resellers\ResellerCollectionPaymentService;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerOpsTelegramService;
use App\Services\Resellers\ResellerPaymentAllocationService;
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

        $openInvoices = OpenInvoiceResolver::openInvoicesWithBalance($customer)->map(fn ($inv) => [
            'id' => $inv->id,
            'number' => $inv->invoice_number,
            'due' => $inv->balanceDue(),
            'due_date' => $inv->due_date?->format('d M Y'),
        ]);

        $profile = app(\App\Services\Resellers\ResellerCustomerProfileService::class)->profileSnapshot($customer);

        return view('reseller.collect-payment', [
            'reseller' => $reseller,
            'customer' => $customer,
            'profile' => $profile,
            'openDue' => OpenInvoiceResolver::totalOpenDue($customer),
            'openInvoices' => $openInvoices,
            'walletBalance' => (float) $customer->account_balance,
            'fifoEnabled' => (bool) config('reseller_billing.payment_allocation.fifo_enabled', true),
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
            'allocation_mode' => ['nullable', 'string', Rule::in([
                ResellerPaymentAllocationService::MODE_SINGLE,
                ResellerPaymentAllocationService::MODE_FIFO,
                ResellerPaymentAllocationService::MODE_ADVANCE,
            ])],
        ]);

        if (! filled($validated['allocation_mode'] ?? null)) {
            $validated['allocation_mode'] = filled($validated['invoice_id'] ?? null)
                ? ResellerPaymentAllocationService::MODE_SINGLE
                : (OpenInvoiceResolver::openInvoicesWithBalance($customer)->isEmpty()
                    ? ResellerPaymentAllocationService::MODE_ADVANCE
                    : ResellerPaymentAllocationService::MODE_FIFO);
        }

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

        app(ResellerOpsTelegramService::class)->paymentCollected(
            $reseller,
            (string) $customer->customer_code,
            (float) $validated['amount'],
            (string) ($validated['allocation_mode'] ?? 'single'),
        );

        $payment = $result['payment'];

        return redirect()
            ->route('reseller.payments.receipt', $payment)
            ->with('status', ($result['message'] ?? 'Payment recorded.').' Receipt: '.$payment->receipt_number);
    }
}
