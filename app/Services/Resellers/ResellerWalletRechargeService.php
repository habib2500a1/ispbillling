<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Models\ResellerStaff;
use App\Models\ResellerWalletRechargeRequest;
use App\Models\User;
use App\Services\Payments\PipraPayCheckoutService;
use App\Services\Payments\PublicCheckoutSession;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerWalletRechargeService
{
    public function isEnabled(): bool
    {
        return (bool) config('reseller.wallet_recharge.enabled', true);
    }

    public function manualEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('reseller.wallet_recharge.manual_enabled', true);
    }

    public function pipraPayEnabled(): bool
    {
        return $this->isEnabled()
            && (bool) config('reseller.wallet_recharge.piprapay_enabled', true)
            && PipraPayCheckoutService::isEnabled();
    }

    /**
     * @return array{min: float, max: float}
     */
    public function amountLimits(): array
    {
        return [
            'min' => (float) config('reseller.wallet_recharge.min_amount', 500),
            'max' => (float) config('reseller.wallet_recharge.max_amount', 500000),
        ];
    }

    public function submitManual(
        Reseller $reseller,
        float $amount,
        string $paymentMethod,
        string $reference,
        ?string $notes = null,
        ?ResellerStaff $staff = null,
    ): ResellerWalletRechargeRequest {
        $this->assertCanRecharge($reseller);
        $this->assertManualEnabled();
        $this->assertAmount($amount);

        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages(['reference' => 'Transaction reference is required.']);
        }

        $duplicate = ResellerWalletRechargeRequest::query()
            ->where('reseller_id', $reseller->id)
            ->where('reference', $reference)
            ->whereIn('status', [ResellerWalletRechargeRequest::STATUS_PENDING, ResellerWalletRechargeRequest::STATUS_APPROVED])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['reference' => 'This transaction reference was already submitted.']);
        }

        $request = ResellerWalletRechargeRequest::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'request_number' => ResellerWalletRechargeRequest::generateNumber((int) $reseller->tenant_id),
            'amount' => round($amount, 2),
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'status' => ResellerWalletRechargeRequest::STATUS_PENDING,
            'notes' => $notes,
            'submitted_by_staff_id' => $staff?->id,
        ]);

        app(ResellerPortalNotifier::class)->walletRechargeSubmitted($reseller, (float) $request->amount, $request->request_number);

        return $request;
    }

    /**
     * @return array{redirect_url: string, request: ResellerWalletRechargeRequest}
     */
    public function initiatePipraPay(Reseller $reseller, float $amount, ?ResellerStaff $staff = null): array
    {
        $this->assertCanRecharge($reseller);
        if (! $this->pipraPayEnabled()) {
            throw ValidationException::withMessages(['gateway' => 'Online wallet top-up is not available.']);
        }

        $this->assertAmount($amount);
        $amountStr = number_format(round($amount, 2), 2, '.', '');
        $orderId = 'RSL-WAL-'.$reseller->id.'-'.now()->format('YmdHis').'-'.substr(bin2hex(random_bytes(4)), 0, 8);

        $request = ResellerWalletRechargeRequest::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'request_number' => ResellerWalletRechargeRequest::generateNumber((int) $reseller->tenant_id),
            'amount' => (float) $amountStr,
            'payment_method' => PaymentGateway::PIPRAPAY,
            'status' => ResellerWalletRechargeRequest::STATUS_PENDING,
            'gateway' => PaymentGateway::PIPRAPAY,
            'checkout_order_id' => $orderId,
            'submitted_by_staff_id' => $staff?->id,
        ]);

        $session = [
            'reseller_id' => $reseller->id,
            'recharge_request_id' => $request->id,
            'amount' => $amountStr,
            'return_to' => 'reseller_portal',
            'payment_type' => PaymentType::RESELLER_WALLET_RECHARGE,
            'gateway' => PaymentGateway::PIPRAPAY,
        ];

        PublicCheckoutSession::put($orderId, $session);

        $checkout = PipraPayCheckoutService::fromConfig()->createChargeForPayer(
            fullName: $reseller->name,
            email: $reseller->email ?: 'partner@isp.local',
            phone: $reseller->phone ?: '01700000000',
            amount: (float) $amountStr,
            orderId: $orderId,
            redirectUrl: PipraPayCheckoutService::publicUrl('/piprapay/success', ['order_id' => $orderId]),
            cancelUrl: PipraPayCheckoutService::publicUrl('/piprapay/cancel', ['order_id' => $orderId]),
            webhookUrl: PipraPayCheckoutService::publicUrl('/piprapay/webhook'),
            metadata: [
                'reseller_id' => $reseller->id,
                'recharge_request_id' => $request->id,
                'order_id' => $orderId,
                'payment_type' => PaymentType::RESELLER_WALLET_RECHARGE,
            ],
        );

        if (! empty($checkout['pp_id'])) {
            $request->forceFill(['gateway_transaction_id' => $checkout['pp_id']])->save();
        }

        return [
            'redirect_url' => $checkout['redirect_url'],
            'request' => $request->fresh(),
        ];
    }

    public function approve(ResellerWalletRechargeRequest $request, User $reviewer): ResellerWalletRechargeRequest
    {
        if ($request->status !== ResellerWalletRechargeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'This recharge request is no longer pending.']);
        }

        return DB::transaction(function () use ($request, $reviewer): ResellerWalletRechargeRequest {
            $request = ResellerWalletRechargeRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== ResellerWalletRechargeRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => 'This recharge request is no longer pending.']);
            }

            $reseller = $request->reseller()->lockForUpdate()->firstOrFail();
            $this->assertCanRecharge($reseller);

            $transfer = app(ResellerBalanceService::class)->credit(
                $reseller,
                (float) $request->amount,
                ResellerBalanceTransfer::TYPE_SELF_RECHARGE,
                $request->reference ?: $request->request_number,
                trim(($request->notes ? $request->notes.' · ' : '').'Wallet recharge '.$request->request_number),
            );

            $request->forceFill([
                'status' => ResellerWalletRechargeRequest::STATUS_APPROVED,
                'balance_transfer_id' => $transfer->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            return $request->fresh();
        });
    }

    public function approveFromGateway(
        ResellerWalletRechargeRequest $request,
        string $gatewayTransactionId,
        array $meta = [],
    ): ResellerWalletRechargeRequest {
        if ($request->status !== ResellerWalletRechargeRequest::STATUS_PENDING) {
            return $request;
        }

        return DB::transaction(function () use ($request, $gatewayTransactionId, $meta): ResellerWalletRechargeRequest {
            $request = ResellerWalletRechargeRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== ResellerWalletRechargeRequest::STATUS_PENDING) {
                return $request;
            }

            $reseller = $request->reseller()->lockForUpdate()->firstOrFail();

            $transfer = app(ResellerBalanceService::class)->credit(
                $reseller,
                (float) $request->amount,
                ResellerBalanceTransfer::TYPE_SELF_RECHARGE,
                $gatewayTransactionId,
                'Online wallet recharge · '.$request->request_number,
            );

            $request->forceFill([
                'status' => ResellerWalletRechargeRequest::STATUS_APPROVED,
                'gateway_transaction_id' => $gatewayTransactionId,
                'balance_transfer_id' => $transfer->id,
                'reviewed_at' => now(),
                'meta' => array_merge($request->meta ?? [], ['gateway' => $meta]),
            ])->save();

            return $request->fresh();
        });
    }

    public function reject(ResellerWalletRechargeRequest $request, User $reviewer, string $reason): ResellerWalletRechargeRequest
    {
        if ($request->status !== ResellerWalletRechargeRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'This recharge request is no longer pending.']);
        }

        $request->forceFill([
            'status' => ResellerWalletRechargeRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        app(ResellerPortalNotifier::class)->walletRechargeRejected(
            $request->reseller,
            (float) $request->amount,
            $request->request_number,
            $reason,
        );

        return $request->fresh();
    }

    public function findPendingByCheckout(?string $orderId, ?string $ppId): ?ResellerWalletRechargeRequest
    {
        if ($orderId !== null && $orderId !== '') {
            $byOrder = ResellerWalletRechargeRequest::query()
                ->where('checkout_order_id', $orderId)
                ->where('status', ResellerWalletRechargeRequest::STATUS_PENDING)
                ->first();
            if ($byOrder !== null) {
                return $byOrder;
            }
        }

        if ($ppId !== null && $ppId !== '') {
            return ResellerWalletRechargeRequest::query()
                ->where('gateway_transaction_id', $ppId)
                ->where('status', ResellerWalletRechargeRequest::STATUS_PENDING)
                ->first();
        }

        return null;
    }

    private function assertCanRecharge(Reseller $reseller): void
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages(['wallet' => 'Wallet recharge is disabled.']);
        }

        if ($reseller->wallet_frozen) {
            throw ValidationException::withMessages(['wallet' => 'Wallet is frozen. Contact admin.']);
        }

        if (! $reseller->is_active || ! $reseller->hasPortalAccess()) {
            throw ValidationException::withMessages(['wallet' => 'Portal access is not active.']);
        }
    }

    private function assertManualEnabled(): void
    {
        if (! $this->manualEnabled()) {
            throw ValidationException::withMessages(['wallet' => 'Manual wallet recharge is disabled.']);
        }
    }

    private function assertAmount(float $amount): void
    {
        $limits = $this->amountLimits();

        if ($amount < $limits['min']) {
            throw ValidationException::withMessages([
                'amount' => 'Minimum recharge is '.number_format($limits['min'], 0).' BDT.',
            ]);
        }

        if ($amount > $limits['max']) {
            throw ValidationException::withMessages([
                'amount' => 'Maximum recharge is '.number_format($limits['max'], 0).' BDT.',
            ]);
        }
    }
}
