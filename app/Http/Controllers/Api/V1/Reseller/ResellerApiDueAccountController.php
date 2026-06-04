<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Resellers\ResellerBillingPolicyService;
use App\Services\Resellers\ResellerDueLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiDueAccountController extends Controller
{
    public function show(
        Request $request,
        ResellerDueLedgerService $ledger,
        ResellerBillingPolicyService $policies,
    ): JsonResponse {
        $reseller = $request->user()->fresh();

        $summary = $ledger->summary($reseller);
        $customerBreakdown = $ledger->customerDueBreakdown($reseller);

        return response()->json([
            'summary' => $summary,
            'customer_breakdown' => $customerBreakdown,
            'aging' => $policies->agingReport($reseller),
        ]);
    }
}
