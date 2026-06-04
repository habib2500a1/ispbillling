<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerWalletTransaction;
use App\Services\Resellers\ResellerQuotaService;
use App\Services\Resellers\ResellerWalletLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiWalletOverviewController extends Controller
{
    public function show(
        Request $request,
        ResellerWalletLedgerService $ledger,
        ResellerQuotaService $quota,
    ): JsonResponse {
        $reseller = $request->user()->fresh();

        $transactions = ResellerWalletTransaction::query()
            ->where('reseller_id', $reseller->id)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'wallet_balance' => (float) $reseller->wallet_balance,
            'bonus_wallet_balance' => (float) $reseller->bonus_wallet_balance,
            'credit_limit' => (float) $reseller->credit_limit,
            'available_main' => $ledger->availableMainBalance($reseller),
            'total_spendable' => $ledger->totalSpendable($reseller),
            'is_low_balance' => $ledger->isLowBalance($reseller),
            'quota' => $quota->usage($reseller),
            'transactions' => $transactions,
        ]);
    }
}
