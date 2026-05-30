<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerPortalDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiDashboardController extends Controller
{
    public function show(Request $request, ResellerPortalDashboardService $dashboard): JsonResponse
    {
        $reseller = $request->user();
        $metrics = $dashboard->metrics($reseller);

        return response()->json([
            'metrics' => $metrics,
            'charts' => $metrics['charts'] ?? [],
            'recent_payments' => $dashboard->recentPayments($reseller, 10),
            'recent_commissions' => ResellerCommission::query()
                ->where('reseller_id', $reseller->id)
                ->with(['customer:id,name,customer_code', 'payment:id,amount'])
                ->latest('earned_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
