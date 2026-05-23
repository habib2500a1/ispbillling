<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerPortalDashboardService;
use Illuminate\View\View;

class ResellerDashboardController extends Controller
{
    public function index(ResellerPortalDashboardService $dashboard): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $metrics = $dashboard->metrics($reseller);

        $recentCommissions = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->with(['customer:id,name,customer_code', 'payment:id,amount,paid_at'])
            ->latest('earned_at')
            ->limit(8)
            ->get();

        return view('reseller.dashboard', [
            'reseller' => $reseller,
            'metrics' => $metrics,
            'recentCommissions' => $recentCommissions,
        ]);
    }
}
