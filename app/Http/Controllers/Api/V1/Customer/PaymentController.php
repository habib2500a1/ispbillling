<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\BkashPaymentController;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function initiate(Request $request, Invoice $invoice, BkashPaymentController $bkash): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        $result = $bkash->prepareMobileCheckout($invoice);

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'payment_url' => $result['bkash_url'],
            'payment_id' => $result['payment_id'],
            'amount' => $result['amount'],
        ]);
    }
}
