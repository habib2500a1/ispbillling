<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\StaffBillingMobileService;
use App\Support\StaffMobileApiAccess;
use App\Support\StaffTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffBillingController extends Controller
{
    use StaffMobileApiAccess;

    public function summary(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staffMobileUser($request);

        return response()->json($billing->summary($user));
    }

    public function due(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staffMobileUser($request);
        $page = max(1, (int) $request->query('page', 1));

        return response()->json($billing->dueList(StaffTenantScope::tenantIdFor($user), $page));
    }

    public function invoices(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staffMobileUser($request);

        return response()->json($billing->invoices(StaffTenantScope::tenantIdFor($user), $request));
    }

    public function collections(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staffMobileUser($request);

        return response()->json($billing->collections(StaffTenantScope::tenantIdFor($user), $request));
    }
}
