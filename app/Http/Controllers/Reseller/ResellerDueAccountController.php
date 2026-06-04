<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\ResellerLedgerEntry;
use App\Services\Resellers\ResellerBillingPolicyService;
use App\Services\Resellers\ResellerDueLedgerService;
use App\Support\ResellerBillingSettlementMode;
use App\Support\ResellerCustomerBillingPolicy;
use Illuminate\View\View;

class ResellerDueAccountController extends Controller
{
    public function index(
        ResellerDueLedgerService $ledger,
        ResellerBillingPolicyService $policies,
    ): View {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user()->fresh();

        $summary = $ledger->summary($reseller);
        $aging = $policies->agingReport($reseller);
        $entries = ResellerLedgerEntry::query()
            ->where('reseller_id', $reseller->id)
            ->latest()
            ->limit(100)
            ->get();

        $customerBreakdown = $ledger->customerDueBreakdown($reseller);
        $subscriberLines = $ledger->subscriberDueLines($reseller);
        $lineTotals = [
            'count' => count($subscriberLines),
            'retail_due' => round(array_sum(array_column($subscriberLines, 'retail_due')), 2),
            'wholesale' => round(array_sum(array_column($subscriberLines, 'wholesale')), 2),
            'with_bill' => count(array_filter($subscriberLines, fn (array $l): bool => $l['invoice_number'] !== null)),
        ];

        return view('reseller.due-account', [
            'reseller' => $reseller,
            'summary' => $summary,
            'aging' => $aging,
            'entries' => $entries,
            'subscriberLines' => $subscriberLines,
            'lineTotals' => $lineTotals,
            'customerBreakdown' => $customerBreakdown,
            'customerDue' => $customerBreakdown['due'],
            'settlementMode' => ResellerBillingSettlementMode::labels()[$reseller->billing_settlement_mode ?? 'postpaid_due'] ?? 'Postpaid due',
            'customerPolicy' => ResellerCustomerBillingPolicy::labels()[$reseller->customer_billing_policy ?? 'reseller_controlled'] ?? '',
        ]);
    }
}
