<?php

namespace App\Services\Billing;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\CollectorCollection;
use App\Models\Payment;
use App\Models\ResellerCommission;
use App\Services\Accounting\AccountingIntegrationService;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Void (cancel) a wrong collection — reverses invoice paid, wallet credit, discount, collector & GL.
 */
final class PaymentVoidService
{
    public function __construct(
        private readonly PaymentAllocationCorrectionService $corrections,
        private readonly AccountingIntegrationService $accounting,
    ) {}

    public function canVoid(Payment $payment): bool
    {
        return $this->voidBlockReason($payment) === null;
    }

    public function voidBlockReason(Payment $payment): ?string
    {
        if ($payment->status === 'void') {
            return 'This payment is already voided.';
        }

        if ($payment->status !== 'completed') {
            return 'Only completed payments can be voided.';
        }

        if (! in_array($payment->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true)) {
            return 'This payment type must be voided from the Payments module.';
        }

        if ($payment->refunds()->where('status', 'completed')->exists()) {
            return 'Payment has refunds — void the refund entries first.';
        }

        $collection = CollectorCollection::query()->where('payment_id', $payment->id)->first();
        if ($collection !== null && (float) $collection->amount_settled > 0.009) {
            return 'Collector cash already settled to office — reverse settlement before voiding.';
        }

        $commission = ResellerCommission::query()->where('payment_id', $payment->id)->first();
        if ($commission !== null && $commission->status === ResellerCommission::STATUS_PAID) {
            return 'Reseller commission already paid — contact admin before voiding.';
        }

        return null;
    }

    public function void(Payment $payment, ?string $reason = null, ?int $voidedBy = null): Payment
    {
        $reason = trim((string) $reason);
        $block = $this->voidBlockReason($payment);
        if ($block !== null) {
            throw ValidationException::withMessages(['payment' => $block]);
        }

        if ($reason !== '' && mb_strlen($reason) < 3) {
            throw ValidationException::withMessages([
                'reason' => 'Please enter a short reason for voiding (at least 3 characters).',
            ]);
        }

        $voidedBy ??= auth()->id();

        return DB::transaction(function () use ($payment, $reason, $voidedBy): Payment {
            $payment = $payment->fresh(['customer', 'invoice']);

            CollectionDiscountApplicator::reverseFromPayment($payment);
            $this->corrections->reverseProcessedPayment($payment);

            CollectorCollection::query()->where('payment_id', $payment->id)->delete();

            ResellerCommission::query()->where('payment_id', $payment->id)->delete();

            $this->accounting->reverseCustomerPayment($payment);

            $meta = $payment->meta ?? [];
            unset($meta['processed'], $meta['processed_at'], $meta['invoice_credited'], $meta['invoice_applied'], $meta['wallet_credit'], $meta['wallet_reason']);
            $meta['voided_at'] = now()->toIso8601String();
            $meta['voided_by'] = $voidedBy;
            if ($reason !== '') {
                $meta['void_reason'] = $reason;
            }

            $payment->forceFill([
                'status' => 'void',
                'notes' => trim(($payment->notes ? $payment->notes."\n" : '').'[VOID] '.($reason !== '' ? $reason : 'Wrong entry removed')),
                'meta' => $meta,
            ])->save();

            if ($payment->customer_id) {
                SyncCustomerNetworkAccessJob::dispatch(
                    (int) $payment->tenant_id,
                    (int) $payment->customer_id,
                )->afterResponse();
            }

            return $payment->fresh(['invoice', 'customer', 'recorder']);
        });
    }
}
