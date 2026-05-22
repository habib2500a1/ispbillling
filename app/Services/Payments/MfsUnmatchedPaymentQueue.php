<?php

namespace App\Services\Payments;

use App\Models\MfsSmsRecord;
use App\Models\PendingGatewayPayment;

/**
 * SMS received but subscriber ID / phone could not be matched — queue for admin assignment.
 */
final class MfsUnmatchedPaymentQueue
{
    /**
     * @param  array{customer: ?\App\Models\Customer, customers: list<\App\Models\Customer>, token: ?string, matched_by: ?string, candidates: list<string>}  $resolved
     */
    public function queueFromSms(MfsSmsRecord $sms, array $resolved): PendingGatewayPayment
    {
        $meta = [
            'sms_record_id' => $sms->id,
            'needs_customer_assignment' => true,
            'reference_match' => 'needs_assignment',
            'bill_payment_state' => \App\Support\MfsSmsBillPaymentState::PENDING_MATCH,
            'reference_tokens' => $resolved['candidates'] ?? [],
            'sender_phone' => $sms->sender_phone,
            'reference_token' => $resolved['token'] ?? ($resolved['candidates'][0] ?? null),
            'device_name' => $sms->device_name,
            'fifo_multi_invoice' => true,
            'raw_message_preview' => mb_substr((string) ($sms->raw_message ?? ''), 0, 240),
        ];

        $existing = PendingGatewayPayment::query()
            ->where('gateway', $sms->gateway)
            ->where('transaction_id', $sms->transaction_id)
            ->first();

        if ($existing !== null) {
            if ($existing->customer_id !== null) {
                return $existing;
            }

            $existing->forceFill([
                'amount' => (float) $sms->amount,
                'status' => PendingGatewayPayment::STATUS_PENDING,
                'meta' => array_merge($existing->meta ?? [], $meta),
            ])->save();

            $this->linkSmsToPending($sms, (int) $existing->id);

            return $existing->fresh() ?? $existing;
        }

        $pending = PendingGatewayPayment::query()->create([
            'tenant_id' => $sms->tenant_id,
            'customer_id' => null,
            'invoice_id' => null,
            'gateway' => $sms->gateway,
            'transaction_id' => $sms->transaction_id,
            'amount' => (float) $sms->amount,
            'status' => PendingGatewayPayment::STATUS_PENDING,
            'checkout_order_id' => 'sms-unmatched-'.$sms->id,
            'meta' => $meta,
        ]);

        $this->linkSmsToPending($sms, (int) $pending->id);

        return $pending;
    }

    public function findPendingForSms(MfsSmsRecord $sms): ?PendingGatewayPayment
    {
        $pendingId = $sms->meta['pending_gateway_id'] ?? null;
        if ($pendingId !== null) {
            $found = PendingGatewayPayment::query()->find($pendingId);
            if ($found !== null) {
                return $found;
            }
        }

        return PendingGatewayPayment::query()
            ->where('gateway', $sms->gateway)
            ->where('transaction_id', $sms->transaction_id)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->orderByDesc('id')
            ->first();
    }

    private function linkSmsToPending(MfsSmsRecord $sms, int $pendingId): void
    {
        $sms->forceFill([
            'meta' => array_merge($sms->meta ?? [], [
                'reference_match' => 'needs_assignment',
                'pending_gateway_id' => $pendingId,
                'bill_payment_state' => \App\Support\MfsSmsBillPaymentState::PENDING_MATCH,
            ]),
        ])->save();
    }
}
