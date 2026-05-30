<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\BillPayment\ClientInvoicePaymentService;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Reseller\ResellerPaymentContext;
use App\Support\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function payables(Request $request, ClientInvoicePaymentService $payments): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json($payments->payablesPayload($customer));
    }

    public function initiate(Request $request, Invoice $invoice, ClientInvoicePaymentService $payments): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $payments->assertCustomerOwnsInvoice($customer, $invoice);

        $validated = $request->validate([
            'gateway' => ['required', 'string', 'in:'.implode(',', ResellerPaymentContext::allowedCheckoutGateways($customer))],
            'amount' => ['prohibited'],
        ]);

        $result = $payments->prepareMobilePayment($invoice, $validated['gateway']);

        return response()->json($result);
    }

    public function prepayInitiate(Request $request, ClientInvoicePaymentService $payments): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $maxMonths = app(CustomerPrepayService::class)->maxMonths();

        $validated = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:'.$maxMonths],
            'gateway' => ['required', 'string', 'in:'.implode(',', ResellerPaymentContext::allowedCheckoutGateways($customer))],
        ]);

        return response()->json(
            $payments->prepareMobilePrepay($customer, (int) $validated['months'], $validated['gateway']),
        );
    }
}
