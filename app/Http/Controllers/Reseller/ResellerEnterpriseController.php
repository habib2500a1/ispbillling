<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerAnnouncement;
use App\Models\ResellerWalletTransaction;
use App\Services\Resellers\ResellerEnterpriseReportService;
use App\Services\Resellers\ResellerQuotaService;
use App\Services\Resellers\ResellerWalletLedgerService;
use App\Support\ResellerPortalPermission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerEnterpriseController extends Controller
{
    public function walletOverview(ResellerWalletLedgerService $ledger, ResellerQuotaService $quota): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $transactions = ResellerWalletTransaction::query()
            ->where('reseller_id', $reseller->id)
            ->latest()
            ->limit(100)
            ->get();

        return view('reseller.enterprise.wallet-overview', [
            'reseller' => $reseller->fresh(),
            'availableMain' => $ledger->availableMainBalance($reseller),
            'totalSpendable' => $ledger->totalSpendable($reseller),
            'isLowBalance' => $ledger->isLowBalance($reseller),
            'quota' => $quota->usage($reseller),
            'transactions' => $transactions,
        ]);
    }

    public function reports(ResellerEnterpriseReportService $reports): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.enterprise.reports', [
            'reseller' => $reseller,
            'revenue' => $reports->revenueReport($reseller),
            'growth' => $reports->customerGrowthReport($reseller),
            'packageSales' => $reports->packageSalesReport($reseller),
            'profitLoss' => $reports->profitLossReport($reseller),
        ]);
    }

    public function announcements(Request $request): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $items = ResellerAnnouncement::query()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('is_active', true)
            ->latest('published_at')
            ->limit(50)
            ->get()
            ->filter(fn (ResellerAnnouncement $a) => $a->isVisibleTo($reseller));

        return view('reseller.enterprise.announcements', [
            'reseller' => $reseller,
            'announcements' => $items,
        ]);
    }

    public function security(Request $request): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.enterprise.security', [
            'reseller' => $reseller,
            'loginLogs' => $reseller->portalLoginLogs()->latest('created_at')->limit(30)->get(),
        ]);
    }
}
