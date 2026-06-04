<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Resellers\ResellerCollectionPaymentService;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Support\ResellerCollectionPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResellerApiPaymentController extends Controller
{
    public function store(Request $request, Customer $customer, ResellerCollectionPaymentService $payments, ResellerCustomerService $customers): JsonResponse
    {
        $reseller = $request->user();
        $customers->assertOwned($reseller, $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', Rule::in(ResellerCollectionPaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:500'],
            'invoice_id' => ['nullable', 'integer'],
        ]);

        $result = $payments->collect($reseller, $customer, $validated);
        $payment = $result['payment'];

        app(ResellerPortalActivityLogger::class)->log($reseller, 'payment.collect', $payment, [
            'amount' => $validated['amount'],
            'method' => $validated['method'] ?? 'cash',
        ]);

        app(\App\Services\Resellers\ResellerPortalNotifier::class)->paymentReceived(
            $reseller,
            $customer->customer_code,
            (float) $validated['amount'],
            $payment->id,
        );

        return response()->json(array_merge($result, [
            'receipt_url' => url('/api/v1/reseller/payments/'.$payment->id.'/receipt/pdf'),
        ]));
    }

    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        $reseller = $request->user();
        $payment->loadMissing('customer');
        abort_unless(
            $payment->customer !== null && (int) $payment->customer->reseller_id === (int) $reseller->id,
            403,
        );

        return response()->json([
            'receipt_url' => url('/api/v1/reseller/payments/'.$payment->id.'/receipt/pdf'),
            'receipt_number' => $payment->receipt_number,
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ]);
    }
}
