<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\CustomerMobileService;
use App\Services\Portal\CustomerBandwidthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function live(Request $request, CustomerBandwidthService $bandwidth, CustomerMobileService $mobile): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'usage' => $mobile->usagePayload($bandwidth->liveStats($customer)),
        ]);
    }
}
