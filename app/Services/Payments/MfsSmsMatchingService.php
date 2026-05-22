<?php

namespace App\Services\Payments;

use App\Models\MfsSmsRecord;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class MfsSmsMatchingService
{
    /**
     * Find an unused SMS ledger row matching gateway + TrxID + amount (PipraPay-style).
     *
     * @return array{sms: ?MfsSmsRecord, matched: bool, reasons: list<string>}
     */
    public function matchForPayment(int $tenantId, string $gateway, string $trxId, float $amount): array
    {
        $reasons = [];
        $trxId = \App\Support\PersonalMfsGateway::normalizeTrxId($trxId);
        $tolerance = (float) config('mfs_personal.amount_tolerance', 0.01);

        $statuses = (bool) config('mfs_personal.sms_ingest.require_sms_approved', true)
            ? [MfsSmsRecord::STATUS_APPROVED]
            : [MfsSmsRecord::STATUS_APPROVED, MfsSmsRecord::STATUS_AWAITING];

        $sms = MfsSmsRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('gateway', $gateway)
            ->where('transaction_id', $trxId)
            ->whereIn('status', $statuses)
            ->lockForUpdate()
            ->first();

        if ($sms === null) {
            $reasons[] = 'no_sms_match';

            return ['sms' => null, 'matched' => false, 'reasons' => $reasons];
        }

        if (abs((float) $sms->amount - $amount) > $tolerance) {
            $reasons[] = 'sms_amount_mismatch';

            return ['sms' => $sms, 'matched' => false, 'reasons' => $reasons];
        }

        return ['sms' => $sms, 'matched' => true, 'reasons' => []];
    }

    public function markUsed(MfsSmsRecord $sms, int $pendingId, int $paymentId): void
    {
        DB::transaction(function () use ($sms, $pendingId, $paymentId): void {
            $locked = MfsSmsRecord::query()->whereKey($sms->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status === MfsSmsRecord::STATUS_USED) {
                throw new \RuntimeException('SMS record already consumed.');
            }

            $locked->forceFill([
                'status' => MfsSmsRecord::STATUS_USED,
                'matched_pending_id' => $pendingId,
                'payment_id' => $paymentId,
                'used_at' => now(),
            ])->save();

            $payment = Payment::withoutGlobalScopes()->with('customer')->find($paymentId);
            if ($payment?->customer !== null) {
                $locked->enrichMatchedCustomerMeta($payment->customer);
            }

            \App\Support\MfsSmsBillPaymentState::refreshMeta($locked->fresh() ?? $locked);
        });
    }
}
