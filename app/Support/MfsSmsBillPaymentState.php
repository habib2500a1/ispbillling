<?php

namespace App\Support;

use App\Models\MfsSmsRecord;
use App\Models\Payment;
use App\Services\Payments\GatewayPaymentVerificationService;

final class MfsSmsBillPaymentState
{
    public const LINKED = 'linked';

    public const PENDING_MATCH = 'pending_match';

    public const DUPLICATE_TRX = 'duplicate_trx';

    public const REJECTED = 'rejected';

    public const AWAITING_SMS = 'awaiting_sms';

    public static function resolve(MfsSmsRecord $record): string
    {
        $cached = $record->meta['bill_payment_state'] ?? null;
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return self::compute($record);
    }

    public static function compute(MfsSmsRecord $record): string
    {
        if ($record->status === MfsSmsRecord::STATUS_REJECTED) {
            return self::REJECTED;
        }

        if ($record->payment_id !== null || $record->status === MfsSmsRecord::STATUS_USED) {
            return self::LINKED;
        }

        if (self::hasDuplicateGatewayPayment($record)) {
            return self::DUPLICATE_TRX;
        }

        if ($record->status === MfsSmsRecord::STATUS_AWAITING) {
            return self::AWAITING_SMS;
        }

        return self::PENDING_MATCH;
    }

    public static function label(string $state): string
    {
        return match ($state) {
            self::LINKED => 'Linked',
            self::PENDING_MATCH => 'Pending match',
            self::DUPLICATE_TRX => 'Duplicate Trx',
            self::REJECTED => 'Rejected',
            self::AWAITING_SMS => 'Awaiting SMS',
            default => 'Pending match',
        };
    }

    public static function color(string $state): string
    {
        return match ($state) {
            self::LINKED => 'success',
            self::PENDING_MATCH, self::AWAITING_SMS => 'warning',
            self::DUPLICATE_TRX, self::REJECTED => 'danger',
            default => 'gray',
        };
    }

    public static function patchMeta(MfsSmsRecord $record, ?string $state = null): array
    {
        $state ??= self::compute($record);

        return ['bill_payment_state' => $state];
    }

    public static function hasDuplicateGatewayPayment(MfsSmsRecord $record): bool
    {
        return Payment::query()
            ->withoutGlobalScopes()
            ->where('gateway', $record->gateway)
            ->where('gateway_transaction_id', $record->transaction_id)
            ->when($record->payment_id !== null, fn ($q) => $q->where('id', '!=', $record->payment_id))
            ->exists();
    }

    public static function refreshMeta(MfsSmsRecord $record): void
    {
        $record->forceFill([
            'meta' => array_merge($record->meta ?? [], self::patchMeta($record)),
        ])->save();
    }

    public static function afterIngest(MfsSmsRecord $record, bool $duplicate, int $matchedPending): void
    {
        if ($record->payment_id !== null || $matchedPending > 0) {
            self::refreshMeta($record->fresh() ?? $record);

            return;
        }

        if ($duplicate && self::hasDuplicateGatewayPayment($record)) {
            $record->forceFill([
                'meta' => array_merge($record->meta ?? [], self::patchMeta($record, self::DUPLICATE_TRX)),
            ])->save();

            return;
        }

        self::refreshMeta($record->fresh() ?? $record);
    }
}
