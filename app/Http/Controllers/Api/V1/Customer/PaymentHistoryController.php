<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    public function index(Request $request, CustomerMobileService $mobile): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $page = max(1, (int) $request->query('page', 1));

        return response()->json($mobile->paymentHistory($customer, $page));
    }
}
