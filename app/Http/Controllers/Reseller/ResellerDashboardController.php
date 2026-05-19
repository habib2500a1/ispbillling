<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerCommission;
use Illuminate\View\View;

class ResellerDashboardController extends Controller
{
    public function index(): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $stats = $reseller->dashboardStats();

        $recentCommissions = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->with(['customer:id,name,customer_code', 'payment:id,amount,paid_at'])
            ->latest('earned_at')
            ->limit(8)
            ->get();

        $activeCustomers = $reseller->customers()
            ->where('status', 'active')
            ->count();

        $overdueInvoices = $reseller->customers()
            ->whereHas('invoices', fn ($q) => $q->whereIn('status', ['open', 'partial']))
            ->count();

        $onlineCustomers = $reseller->customers()
            ->where('is_ppp_online', true)
            ->count();

        return view('reseller.dashboard', [
            'reseller' => $reseller,
            'stats' => $stats,
            'activeCustomers' => $activeCustomers,
            'onlineCustomers' => $onlineCustomers,
            'overdueInvoices' => $overdueInvoices,
            'recentCommissions' => $recentCommissions,
        ]);
    }
}
