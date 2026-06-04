<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\MfsSmsRecord;
use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Services\Billing\BillingDueRealtimeSync;
use App\Services\Billing\CollectionDiscountApplicator;
use App\Services\Billing\PaymentAllocationCorrectionService;
use App\Services\Billing\PaymentVoidService;
use App\Support\MfsSmsBillPaymentState;
use App\Support\MfsSmsCustomerSnapshot;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move a wrongly auto-matched MFS/gateway payment to the correct subscriber (undo wrong + apply right).
 */
final class MfsPaymentTransferService
{
    public function __construct(
        private readonly PaymentAllocationCorrectionService $corrections,
        private readonly PaymentVoidService $voidService,
    ) {}

    public function canTransfer(Payment $payment): ?string
    {
        if (! $payment->exists) {
            return 'No payment linked to this SMS row.';
        }

        if ($payment->status !== 'completed') {
            return 'Only completed payments can be transferred.';
        }

        if (($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::PAYMENT) {
            return 'Only standard payment entries can be transferred.';
        }

        if (! filled($payment->gateway) || ! in_array($payment->gateway, [PaymentGateway::BKASH, PaymentGateway::NAGAD, PaymentGateway::ROCKET], true)) {
            return 'Only bKash / Nagad / Rocket SMS payments can be transferred here.';
        }

        if ($payment->refunds()->where('status', 'completed')->exists()) {
            return 'Refund this payment first, then transfer.';
        }

        return $this->voidService->voidBlockReason($payment);
    }

    public function transfer(
        Payment $payment,
        int $toCustomerId,
        ?string $notes = null,
        ?int $reviewerId = null,
    ): Payment {
        $block = $this->canTransfer($payment);
        if ($block !== null) {
            throw ValidationException::withMessages(['payment' => $block]);
        }

        $fromCustomerId = (int) $payment->customer_id;
        if ($fromCustomerId === $toCustomerId) {
            throw ValidationException::withMessages([
                'customer_id' => 'Choose a different subscriber than the current one.',
            ]);
        }

        $target = Customer::withoutGlobalScopes()
            ->where('tenant_id', $payment->tenant_id)
            ->whereKey($toCustomerId)
            ->first();

        if ($target === null) {
            throw ValidationException::withMessages([
                'customer_id' => 'Subscriber not found.',
            ]);
        }

        $reviewerId ??= auth()->id();
        $notes = trim((string) $notes);

        $payment = DB::transaction(function () use ($payment, $target, $fromCustomerId, $notes, $reviewerId): Payment {
            $payment = $payment->fresh(['customer', 'invoice']);

            CollectionDiscountApplicator::reverseFromPayment($payment);
            $this->corrections->reverseProcessedPayment($payment);

            $meta = $payment->meta ?? [];
            $fifo = (bool) ($meta['fifo_multi_invoice'] ?? false);
            unset(
                $meta['processed'],
                $meta['processed_at'],
                $meta['invoice_credited'],
                $meta['invoice_applied'],
                $meta['invoice_allocations'],
                $meta['wallet_credit'],
                $meta['wallet_reason'],
            );

            $fromCustomer = $payment->customer;
            $meta['transferred_at'] = now()->toIso8601String();
            $meta['transferred_by'] = $reviewerId;
            $meta['transferred_from_customer_id'] = $fromCustomerId;
            $meta['transferred_from_customer_code'] = $fromCustomer?->customer_code;
            $meta['transferred_from_customer_name'] = $fromCustomer?->name;
            $meta['transferred_to_customer_id'] = $target->id;
            $meta['transferred_to_customer_code'] = $target->customer_code;
            if ($notes !== '') {
                $meta['transfer_note'] = $notes;
            }
            if ($fifo) {
                $meta['fifo_multi_invoice'] = true;
            }

            $payment->forceFill([
                'customer_id' => $target->id,
                'invoice_id' => null,
                'notes' => trim(($payment->notes ? $payment->notes."\n" : '').'[TRANSFER] From '.($fromCustomer?->customer_code ?? $fromCustomerId).' → '.$target->customer_code.($notes !== '' ? ': '.$notes : '')),
                'recorded_by' => $reviewerId,
                'meta' => $meta,
            ])->save();

            PaymentProcessor::processCompletedPayment($payment->fresh(['customer', 'invoice']));

            $this->syncSmsAndPending($payment->fresh(), $target, $fromCustomerId, $reviewerId);

            return $payment->fresh(['customer', 'invoice']);
        });

        $wrong = Customer::withoutGlobalScopes()->find($fromCustomerId);
        if ($wrong !== null) {
            BillingDueRealtimeSync::afterPayment($wrong, queueNetwork: false);
        }
        BillingDueRealtimeSync::afterPayment($payment->customer ?? $target, queueNetwork: false);

        return $payment;
    }

    private function syncSmsAndPending(
        Payment $payment,
        Customer $target,
        int $fromCustomerId,
        ?int $reviewerId,
    ): void {
        $sms = MfsSmsRecord::query()
            ->where('payment_id', $payment->id)
            ->orWhere(function ($q) use ($payment): void {
                if (filled($payment->gateway) && filled($payment->gateway_transaction_id)) {
                    $q->where('gateway', $payment->gateway)
                        ->where('transaction_id', $payment->gateway_transaction_id);
                }
            })
            ->orderByDesc('id')
            ->first();

        if ($sms !== null) {
            $sms->forceFill([
                'payment_id' => $payment->id,
                'status' => MfsSmsRecord::STATUS_USED,
                'meta' => array_merge($sms->meta ?? [], MfsSmsCustomerSnapshot::from($target), [
                    'reference_match' => 'admin_transferred',
                    'matched_by' => 'admin_transfer',
                    'transferred_from_customer_id' => $fromCustomerId,
                    'transferred_by' => $reviewerId,
                    'bill_payment_state' => MfsSmsBillPaymentState::LINKED,
                ]),
            ])->save();
        }

        $pending = PendingGatewayPayment::query()
            ->where('gateway', $payment->gateway)
            ->where('transaction_id', $payment->gateway_transaction_id)
            ->first();

        if ($pending !== null) {
            $pending->forceFill([
                'customer_id' => $target->id,
                'payment_id' => $payment->id,
                'status' => PendingGatewayPayment::STATUS_APPROVED,
                'meta' => array_merge($pending->meta ?? [], [
                    'matched_by' => 'admin_transfer',
                    'transferred_from_customer_id' => $fromCustomerId,
                    'transferred_by' => $reviewerId,
                ]),
            ])->save();
        }
    }
}
