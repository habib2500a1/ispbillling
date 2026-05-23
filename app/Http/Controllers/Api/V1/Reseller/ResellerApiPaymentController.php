<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCollectionPaymentService;
use App\Services\Resellers\ResellerCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiPaymentController extends Controller
{
    public function store(Request $request, Customer $customer, ResellerCollectionPaymentService $payments, ResellerCustomerService $customers): JsonResponse
    {
        $reseller = $request->user();
        $customers->assertOwned($reseller, $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:500'],
            'invoice_id' => ['nullable', 'integer'],
        ]);

        $result = $payments->collect($reseller, $customer, $validated);

        return response()->json($result);
    }
}
