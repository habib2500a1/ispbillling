<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerBalanceTransfer;
use App\Models\ResellerWalletRechargeRequest;
use App\Services\Resellers\ResellerWalletRechargeService;
use App\Support\ResellerApiContext;
use App\Support\ResellerCollectionPaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResellerApiWalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $reseller = $request->user();
        $recharges = app(ResellerWalletRechargeService::class);

        $transfers = ResellerBalanceTransfer::query()
            ->where(function ($q) use ($reseller): void {
                $q->where('to_reseller_id', $reseller->id)->orWhere('from_reseller_id', $reseller->id);
            })
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'balance' => (float) $reseller->wallet_balance,
            'frozen' => (bool) $reseller->wallet_frozen,
            'recharge' => [
                'enabled' => $recharges->isEnabled() && ! $reseller->wallet_frozen,
                'manual_enabled' => $recharges->manualEnabled(),
                'piprapay_enabled' => $recharges->pipraPayEnabled(),
                'limits' => $recharges->amountLimits(),
            ],
            'transfers' => $transfers,
            'recharge_requests' => ResellerWalletRechargeRequest::query()
                ->where('reseller_id', $reseller->id)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function submitRecharge(Request $request, ResellerWalletRechargeService $recharges): JsonResponse
    {
        $reseller = $request->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'payment_method' => ['required', 'string', Rule::in(ResellerCollectionPaymentMethod::values())],
            'reference' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = $recharges->submitManual(
            $reseller,
            (float) $validated['amount'],
            $validated['payment_method'],
            $validated['reference'],
            $validated['notes'] ?? null,
            app(ResellerApiContext::class)->staff(),
        );

        return response()->json([
            'message' => 'Wallet top-up submitted for admin approval.',
            'request' => $row,
        ], 201);
    }

    public function pipraPay(Request $request, ResellerWalletRechargeService $recharges): JsonResponse
    {
        $reseller = $request->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
        ]);

        $checkout = $recharges->initiatePipraPay(
            $reseller,
            (float) $validated['amount'],
            app(ResellerApiContext::class)->staff(),
        );

        return response()->json([
            'payment_url' => $checkout['redirect_url'],
            'request' => $checkout['request'],
        ]);
    }
}
