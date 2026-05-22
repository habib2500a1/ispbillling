<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Mobile\StaffBillingMobileService;
use App\Services\Reports\AnalyticsReportService;
use App\Support\StaffTenantScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffReportsController extends Controller
{
    public function expiring(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $days = max(1, min(90, (int) $request->query('days', 7)));
        $until = now()->addDays($days)->endOfDay();

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', StaffTenantScope::tenantIdFor($user))
            ->whereNotNull('service_expires_at')
            ->where('service_expires_at', '<=', $until)
            ->where('service_expires_at', '>=', now()->startOfDay())
            ->with('package:id,name')
            ->orderBy('service_expires_at')
            ->limit(200)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'phone' => $c->phone,
                'package' => $c->package?->name,
                'status' => $c->status,
                'service_expires_at' => $c->service_expires_at?->toDateString(),
                'days_left' => max(0, now()->startOfDay()->diffInDays($c->service_expires_at, false)),
            ]);

        return response()->json(['data' => $customers->values(), 'days' => $days]);
    }

    public function collections(Request $request, AnalyticsReportService $analytics): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfMonth()
            : now()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfMonth()
            : now()->endOfMonth();

        $report = $analytics->collectionReport($from, $to, StaffTenantScope::tenantIdFor($user));

        return response()->json(['report' => $report]);
    }

    public function due(Request $request, StaffBillingMobileService $billing): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json($billing->dueList(StaffTenantScope::tenantIdFor($user), max(1, (int) $request->query('page', 1))));
    }
}
