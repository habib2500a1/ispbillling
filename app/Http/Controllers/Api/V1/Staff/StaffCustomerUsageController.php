<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Mobile\CustomerMobileService;
use App\Services\Portal\CustomerBandwidthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCustomerUsageController extends Controller
{
    public function live(Request $request, int $customer, CustomerBandwidthService $bandwidth, CustomerMobileService $mobile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['super-admin', 'isp-admin', 'admin', 'isp-manager', 'branch-manager', 'cashier', 'collector']),
            403,
        );

        $model = Customer::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($customer)
            ->firstOrFail();

        $stats = $bandwidth->liveStats($model);

        return response()->json([
            'usage' => $mobile->usagePayload($stats, $model),
            'customer' => [
                'id' => $model->id,
                'customer_code' => $model->customer_code,
                'name' => $model->name,
                'is_online' => $model->isPppOnline(),
                'package' => $model->package?->name,
            ],
        ]);
    }
}
