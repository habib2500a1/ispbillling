<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerBalanceTransfer;
use App\Services\Resellers\ResellerWalletRechargeService;
use App\Support\ResellerCollectionPaymentMethod;
use App\Support\ResellerPortalSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResellerWalletController extends Controller
{
    public function index(ResellerWalletRechargeService $recharges): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $transfers = ResellerBalanceTransfer::query()
            ->where(function ($q) use ($reseller): void {
                $q->where('to_reseller_id', $reseller->id)
                    ->orWhere('from_reseller_id', $reseller->id);
            })
            ->latest()
            ->limit(50)
            ->get();

        return view('reseller.wallet', [
            'reseller' => $reseller->fresh(),
            'transfers' => $transfers,
            'walletFrozen' => (bool) $reseller->wallet_frozen,
            'rechargeEnabled' => $recharges->isEnabled() && ! $reseller->wallet_frozen,
            'manualRechargeEnabled' => $recharges->manualEnabled(),
            'pipraPayEnabled' => $recharges->pipraPayEnabled(),
            'rechargeLimits' => $recharges->amountLimits(),
            'paymentMethods' => collect(ResellerCollectionPaymentMethod::options())
                ->except([\App\Support\PaymentGateway::CASH, \App\Support\PaymentGateway::OTHER])
                ->all(),
            'rechargeRequests' => $reseller->walletRechargeRequests()->latest()->limit(15)->get(),
        ]);
    }

    public function storeRecharge(Request $request, ResellerWalletRechargeService $recharges): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'payment_method' => ['required', 'string', Rule::in(ResellerCollectionPaymentMethod::values())],
            'reference' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $recharges->submitManual(
            $reseller,
            (float) $validated['amount'],
            $validated['payment_method'],
            $validated['reference'],
            $validated['notes'] ?? null,
            app(ResellerPortalSession::class)->staff(),
        );

        return back()->with('status', 'Wallet top-up submitted. Admin will verify your payment and credit your wallet.');
    }

    public function pipraPay(Request $request, ResellerWalletRechargeService $recharges): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
        ]);

        $checkout = $recharges->initiatePipraPay(
            $reseller,
            (float) $validated['amount'],
            app(ResellerPortalSession::class)->staff(),
        );

        return redirect()->away($checkout['redirect_url']);
    }
}
