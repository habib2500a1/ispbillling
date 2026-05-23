<?php

namespace App\Services\Resellers;

use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Models\ResellerCommission;
use App\Support\PaymentType;

final class ResellerCommissionService
{
    public function accrueFromPayment(Payment $payment): ?ResellerCommission
    {
        if ($payment->status !== 'completed') {
            return null;
        }

        if (! in_array($payment->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true)) {
            return null;
        }

        $customer = $payment->customer;
        if ($customer === null || $customer->reseller_id === null) {
            return null;
        }

        if (ResellerCommission::query()->where('payment_id', $payment->id)->exists()) {
            return ResellerCommission::query()->where('payment_id', $payment->id)->first();
        }

        $reseller = Reseller::query()->withoutGlobalScopes()->find($customer->reseller_id);
        if ($reseller === null || ! $reseller->is_active) {
            return null;
        }

        $gross = (float) $payment->amount;
        $commission = $this->calculateCommission($reseller, $gross);
        $parentShare = 0.0;

        if ($reseller->parent_id && (float) $reseller->revenue_share_percent > 0) {
            $parentShare = round($commission * ((float) $reseller->revenue_share_percent / 100), 2);
        }

        $record = ResellerCommission::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'invoice_id' => $payment->invoice_id,
            'gross_amount' => $gross,
            'commission_amount' => $commission,
            'parent_share_amount' => $parentShare,
            'status' => ResellerCommission::STATUS_PENDING,
            'earned_at' => now(),
        ]);

        app(ResellerCommissionNotifier::class)->notifyAccrued($record);

        return $record;
    }

    public function calculateCommission(Reseller $reseller, float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        if ($reseller->commission_type === 'fixed') {
            return min($gross, (float) $reseller->commission_value);
        }

        return round($gross * ((float) $reseller->commission_value / 100), 2);
    }

    public function payoutToWallet(ResellerCommission $commission): void
    {
        if ($commission->status !== ResellerCommission::STATUS_PENDING) {
            return;
        }

        $reseller = $commission->reseller;
        if ($reseller === null) {
            return;
        }

        $amount = (float) $commission->commission_amount;
        if ($amount <= 0) {
            $commission->update(['status' => ResellerCommission::STATUS_PAID, 'paid_at' => now()]);

            return;
        }

        app(ResellerBalanceService::class)->credit(
            $reseller,
            $amount,
            ResellerBalanceTransfer::TYPE_COMMISSION_PAYOUT,
            'Commission #'.$commission->id,
            'Auto payout from payment #'.$commission->payment_id,
        );

        $commission->update([
            'status' => ResellerCommission::STATUS_PAID,
            'paid_at' => now(),
        ]);

        app(ResellerCommissionNotifier::class)->notifyPaid($commission->fresh());

        $this->payoutParentShare($commission->fresh());
    }

    public function payoutParentShare(ResellerCommission $commission): void
    {
        $parentShare = (float) $commission->parent_share_amount;
        if ($parentShare <= 0) {
            return;
        }

        $reseller = $commission->reseller;
        $parent = $reseller?->parent;
        if ($parent === null || ! $parent->is_active) {
            return;
        }

        if (ResellerBalanceTransfer::query()
            ->where('transfer_type', ResellerBalanceTransfer::TYPE_PARENT_SHARE)
            ->where('reference', 'PARENT-'.$commission->id)
            ->exists()) {
            return;
        }

        app(ResellerBalanceService::class)->credit(
            $parent,
            $parentShare,
            ResellerBalanceTransfer::TYPE_PARENT_SHARE,
            'PARENT-'.$commission->id,
            'Revenue share from '.$reseller->code.' · commission #'.$commission->id,
        );
    }
}
