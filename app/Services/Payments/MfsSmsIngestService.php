<?php

namespace App\Services\Payments;

use App\Models\MfsSmsRecord;
use App\Support\PaymentGateway;
use App\Support\PersonalMfsGateway;
use Illuminate\Support\Facades\Log;

final class MfsSmsIngestService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @return array{0: MfsSmsRecord, 1: bool, 2: int} record, wasDuplicate, matchedPendingCount
     */
    public function ingest(int $tenantId, array $payload): array
    {
        $gateway = strtolower((string) ($payload['gateway'] ?? ''));
        if (! in_array($gateway, [PaymentGateway::BKASH, PaymentGateway::NAGAD, PaymentGateway::ROCKET], true)) {
            throw new \InvalidArgumentException('Unsupported gateway.');
        }

        $trxId = PersonalMfsGateway::normalizeTrxId((string) ($payload['transaction_id'] ?? ''));
        if ($trxId === '') {
            throw new \InvalidArgumentException('Transaction ID is required.');
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        $existing = MfsSmsRecord::query()
            ->where('tenant_id', $tenantId)
            ->where('gateway', $gateway)
            ->where('transaction_id', $trxId)
            ->first();

        if ($existing !== null) {
            Log::info('mfs_sms.ingest_duplicate', ['id' => $existing->id, 'trx' => $trxId]);

            if ($existing->status === MfsSmsRecord::STATUS_USED) {
                $existing = $existing->fresh() ?? $existing;
                \App\Support\MfsSmsBillPaymentState::afterIngest($existing, true, 0);

                return [$existing, true, 0];
            }

            $matched = 0;
            if ($existing->status === MfsSmsRecord::STATUS_APPROVED) {
                $matched = app(MfsSmsAutoApprovalService::class)->processApprovedSms($existing);
            }
            if ($matched === 0) {
                $matched = app(GatewayPaymentVerificationService::class)->retryAllPendingMatches($tenantId, 20);
            }

            $existing = $existing->fresh() ?? $existing;
            \App\Support\MfsSmsBillPaymentState::afterIngest($existing, true, $matched);

            return [$existing, true, $matched];
        }

        $status = (bool) config('mfs_personal.sms_ingest.auto_approve_sms', false)
            ? MfsSmsRecord::STATUS_APPROVED
            : MfsSmsRecord::STATUS_AWAITING;

        $meta = [
            'ingested_via' => 'device_api',
            'parsed' => $payload['parsed'] ?? null,
        ];
        if (isset($payload['customer_reference']) && trim((string) $payload['customer_reference']) !== '') {
            $meta['customer_reference'] = trim((string) $payload['customer_reference']);
        }

        $record = MfsSmsRecord::query()->create([
            'tenant_id' => $tenantId,
            'device_name' => $payload['device_name'] ?? null,
            'gateway' => $gateway,
            'sender_type' => $payload['sender_type'] ?? 'personal',
            'sender_phone' => $this->normalizePhone($payload['sender_phone'] ?? null),
            'merchant_phone' => $this->normalizePhone($payload['merchant_phone'] ?? null),
            'transaction_id' => $trxId,
            'amount' => $amount,
            'balance_after' => isset($payload['balance_after']) ? round((float) $payload['balance_after'], 2) : null,
            'status' => $status,
            'raw_message' => isset($payload['raw_message']) ? (string) $payload['raw_message'] : null,
            'sms_received_at' => isset($payload['received_at']) ? now()->parse($payload['received_at']) : now(),
            'meta' => $meta,
        ]);

        $matched = 0;
        if ($status === MfsSmsRecord::STATUS_APPROVED) {
            $matched = app(MfsSmsAutoApprovalService::class)->processApprovedSms($record);
            if ($matched > 0) {
                Log::info('mfs_sms.auto_matched', ['sms_id' => $record->id, 'trx' => $trxId, 'count' => $matched]);
            }
        }

        $record = $record->fresh() ?? $record;
        \App\Support\MfsSmsBillPaymentState::afterIngest($record, false, $matched);

        return [$record, false, $matched];
    }

    public function approve(MfsSmsRecord $record): void
    {
        if ($record->status === MfsSmsRecord::STATUS_USED) {
            throw new \RuntimeException('SMS record already used.');
        }

        $record->forceFill(['status' => MfsSmsRecord::STATUS_APPROVED])->save();
        app(MfsSmsAutoApprovalService::class)->processApprovedSms($record->fresh() ?? $record);
    }

    public function reject(MfsSmsRecord $record, ?string $reason = null): void
    {
        $record->forceFill([
            'status' => MfsSmsRecord::STATUS_REJECTED,
            'meta' => array_merge($record->meta ?? [], ['reject_reason' => $reason]),
        ])->save();
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }
}
