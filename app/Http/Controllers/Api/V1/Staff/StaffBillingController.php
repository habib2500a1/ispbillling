<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\StaffBillingMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffBillingController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'cashier', 'collector', 'branch-manager', 'isp-manager',
    ];

    public function summary(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staff($request);

        return response()->json($billing->summary($user));
    }

    public function due(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staff($request);
        $page = max(1, (int) $request->query('page', 1));

        return response()->json($billing->dueList((int) $user->tenant_id, $page));
    }

    public function invoices(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staff($request);

        return response()->json($billing->invoices((int) $user->tenant_id, $request));
    }

    public function collections(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $this->staff($request);

        return response()->json($billing->collections((int) $user->tenant_id, $request));
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(self::ACCESS_ROLES), 403);

        return $user;
    }
}
