<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiCustomerActionController extends Controller
{
    public function renew(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): JsonResponse
    {
        $result = $lifecycle->renew($request->user(), $customer, $request);

        return response()->json($result);
    }

    public function suspend(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): JsonResponse
    {
        $result = $lifecycle->suspend($request->user(), $customer, $request);

        return response()->json($result);
    }

    public function reconnect(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): JsonResponse
    {
        $result = $lifecycle->reconnect($request->user(), $customer, $request);

        return response()->json($result);
    }

    public function changePassword(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): JsonResponse
    {
        $result = $lifecycle->changePassword($request->user(), $customer, $request);

        return response()->json($result);
    }
}
