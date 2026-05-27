<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\MfsSmsRecord;
use App\Models\PendingGatewayPayment;
use App\Services\Billing\OpenInvoiceResolver;
use App\Support\PersonalMfsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Auto-approve personal MFS payments from SMS + reference (ID/PPPoE) or registered sender phone.
 */
final class MfsSmsAutoApprovalService
{
    public function __construct(
        private readonly MfsCustomerReferenceMatcher $matcher,
        private readonly GatewayPaymentVerificationService $verification,
        private readonly MfsSmsMatchingService $smsMatching,
    ) {}

    public function processApprovedSms(MfsSmsRecord $sms): int
    {
        if ($sms->status !== MfsSmsRecord::STATUS_APPROVED) {
            return 0;
        }

        $matched = $this->verification->matchPendingForSms($sms);
        if ($matched > 0) {
            return $matched;
        }

        if (! (bool) config('mfs_personal.sms_ingest.auto_approve_by_reference', true)) {
            return 0;
        }

        if (! PersonalMfsGateway::autoVerifyEnabled($sms->gateway)) {
            return 0;
        }

        if ($sms->status === MfsSmsRecord::STATUS_USED) {
            return 0;
        }

        return $this->approveByReference($sms);
    }

    public function approveByReference(MfsSmsRecord $sms): int
    {
        if ($sms->payment_id !== null || $sms->status === MfsSmsRecord::STATUS_USED) {
            return 0;
        }

        $message = (string) ($sms->raw_message ?? '');
        $explicit = (string) ($sms->meta['customer_reference'] ?? '');
        $resolved = $this->matcher->resolve(
            (int) $sms->tenant_id,
            $message,
            $explicit !== '' ? $explicit : null,
            $sms->transaction_id,
            $sms->sender_phone,
        );

        if ($resolved['matched_by'] === 'sms_sender_phone_split' && $resolved['customers'] !== []) {
            return $this->approveSplitAcrossCustomers($sms, $resolved);
        }

        $customer = $resolved['customer'];
        if ($customer === null) {
            app(MfsUnmatchedPaymentQueue::class)->queueFromSms($sms, $resolved);

            return 0;
        }

        if ($this->verification->isDuplicateGatewayTransaction($sms->gateway, $sms->transaction_id)) {
            $sms->forceFill([
                'meta' => array_merge($sms->meta ?? [], [
                    'bill_payment_state' => \App\Support\MfsSmsBillPaymentState::DUPLICATE_TRX,
                ]),
            ])->save();

            return 0;
        }

        return $this->finalizeForCustomer(
            $sms,
            $customer,
            (string) ($resolved['token'] ?? ''),
            (string) ($resolved['matched_by'] ?? 'sms_reference'),
            $sms->transaction_id,
        ) ? 1 : 0;
    }

    /**
     * Same registered phone linked to multiple subscriber IDs — split amount across open dues (FIFO).
     *
     * @param  array{customers: list<Customer>, token: ?string, matched_by: ?string}  $resolved
     */
    private function approveSplitAcrossCustomers(MfsSmsRecord $sms, array $resolved): int
    {
        $fresh = $sms->fresh();
        if ($fresh === null || $fresh->status === MfsSmsRecord::STATUS_USED) {
            return 0;
        }

        $remaining = round((float) $fresh->amount, 2);
        $approved = 0;
        $splits = [];

        foreach ($resolved['customers'] as $customer) {
            if ($remaining <= 0.009) {
                break;
            }

            $due = OpenInvoiceResolver::totalOpenDue($customer);
            if ($due <= 0.009) {
                continue;
            }

            $slice = round(min($remaining, $due), 2);
            if ($slice <= 0.009) {
                continue;
            }

            $trxSuffix = $approved === 0 ? '' : '-C'.$customer->id;
            $trxId = $fresh->transaction_id.$trxSuffix;

            if ($this->verification->isDuplicateGatewayTransaction($fresh->gateway, $trxId)) {
                continue;
            }

            if ($this->finalizeForCustomer(
                $fresh,
                $customer,
                (string) ($resolved['token'] ?? $fresh->sender_phone),
                'sms_sender_phone_split',
                $trxId,
                $slice,
                markSmsUsed: false,
            )) {
                $approved++;
                $remaining = round($remaining - $slice, 2);
                $splits[] = [
                    'customer_id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                    'amount' => $slice,
                    'transaction_id' => $trxId,
                ];
            }
        }

        if ($approved === 0) {
            $this->storeMatchMeta($fresh, [
                'reference_match' => 'ambiguous_or_none',
                'sender_phone' => $fresh->sender_phone,
                'split_attempted' => true,
            ]);

            return 0;
        }

        if ($remaining > 0.009 && $resolved['customers'] !== []) {
            $last = $resolved['customers'][array_key_last($resolved['customers'])];
            $trxId = $fresh->transaction_id.'-ADV'.$last->id;
            if (! $this->verification->isDuplicateGatewayTransaction($fresh->gateway, $trxId)) {
                $this->finalizeForCustomer(
                    $fresh,
                    $last,
                    (string) ($resolved['token'] ?? ''),
                    'sms_sender_phone_split_surplus',
                    $trxId,
                    $remaining,
                    markSmsUsed: false,
                );
                $splits[] = [
                    'customer_id' => $last->id,
                    'amount' => $remaining,
                    'transaction_id' => $trxId,
                    'type' => 'advance',
                ];
            }
        }

        $pending = PendingGatewayPayment::query()
            ->where('gateway', $fresh->gateway)
            ->where('transaction_id', 'like', $fresh->transaction_id.'%')
            ->latest('id')
            ->first();

        $paymentId = (int) (PendingGatewayPayment::query()
            ->where('gateway', $fresh->gateway)
            ->where('transaction_id', $fresh->transaction_id)
            ->value('payment_id') ?? 0);

        if ($pending !== null && $paymentId > 0) {
            $this->smsMatching->markUsed($fresh->fresh() ?? $fresh, (int) $pending->id, $paymentId);
        } else {
            $fresh->forceFill([
                'status' => MfsSmsRecord::STATUS_USED,
                'used_at' => now(),
            ])->save();
        }

        $this->storeMatchMeta($fresh->fresh() ?? $fresh, [
            'reference_match' => 'auto_approved',
            'matched_by' => 'sms_sender_phone_split',
            'payment_splits' => $splits,
        ]);

        Log::info('mfs_sms.auto_approved_split', ['sms_id' => $fresh->id, 'splits' => $splits]);

        return $approved;
    }

    private function finalizeForCustomer(
        MfsSmsRecord $sms,
        Customer $customer,
        string $token,
        string $matchedBy,
        string $trxId,
        ?float $amountOverride = null,
        bool $markSmsUsed = true,
    ): bool {
        $fresh = $sms->fresh() ?? $sms;
        if ($fresh->status === MfsSmsRecord::STATUS_USED && $markSmsUsed) {
            return false;
        }

        $amount = round($amountOverride ?? (float) $fresh->amount, 2);
        $checks = [
            'auto_ok' => true,
            'sms_matched' => true,
            'sms_record_id' => $fresh->id,
            'reference_token' => $token,
            'matched_by' => $matchedBy,
            'fifo_multi_invoice' => true,
            'reasons' => [],
        ];

        $result = $this->verification->finalizeAutoApproval(
            gateway: $fresh->gateway,
            orderId: 'sms-ref-'.$fresh->id.($trxId !== $fresh->transaction_id ? '-'.$customer->id : ''),
            trxId: $trxId,
            tenantId: (int) $fresh->tenant_id,
            customerId: (int) $customer->id,
            invoiceId: null,
            amount: $amount,
            checks: $checks,
        );

        if (($result['status'] ?? '') !== 'approved') {
            return false;
        }

        $payment = $result['payment'] ?? null;
        if ($payment === null) {
            return false;
        }

        if ($markSmsUsed) {
            $smsAfter = $fresh->fresh() ?? $fresh;

            if ($smsAfter->status !== MfsSmsRecord::STATUS_USED) {
                $pending = PendingGatewayPayment::query()
                    ->where('gateway', $fresh->gateway)
                    ->where('transaction_id', $trxId)
                    ->latest('id')
                    ->first();

                if ($pending !== null) {
                    $this->smsMatching->markUsed($smsAfter, (int) $pending->id, (int) $payment->id);
                    $smsAfter = $smsAfter->fresh() ?? $smsAfter;
                }
            }

            $this->storeMatchMeta($smsAfter, array_merge(
                \App\Support\MfsSmsCustomerSnapshot::from($customer),
                [
                    'reference_match' => 'auto_approved',
                    'reference_token' => $token,
                    'matched_by' => $matchedBy,
                ],
            ));

            Log::info('mfs_sms.auto_approved_by_reference', [
                'sms_id' => $fresh->id,
                'customer_id' => $customer->id,
                'token' => $token,
                'matched_by' => $matchedBy,
                'trx' => $trxId,
            ]);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function storeMatchMeta(MfsSmsRecord $sms, array $patch): void
    {
        $sms->forceFill([
            'meta' => array_merge($sms->meta ?? [], $patch),
        ])->save();
    }
}
