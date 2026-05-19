<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\PendingGatewayPayment;
use App\Support\PaymentGateway;
use App\Support\PaymentType;

final class PipraPayCheckoutStore
{
    /**
     * @param  array{invoice_id?: int|null, customer_id: int, amount: string, return_to: string, payment_type: string, gateway: string}  $session
     */
    public static function persist(string $orderId, array $session, ?string $ppId = null): void
    {
        $customer = Customer::query()->withoutGlobalScopes()->find((int) $session['customer_id']);
        if ($customer === null) {
            return;
        }

        $meta = ['checkout_session' => $session];
        if ($ppId !== null && $ppId !== '') {
            $meta['pp_id'] = $ppId;
        }

        PendingGatewayPayment::query()->updateOrCreate(
            [
                'gateway' => PaymentGateway::PIPRAPAY,
                'transaction_id' => $ppId ?: $orderId,
            ],
            [
                'tenant_id' => $customer->tenant_id,
                'customer_id' => (int) $session['customer_id'],
                'invoice_id' => $session['invoice_id'] ?? null,
                'amount' => (float) $session['amount'],
                'status' => PendingGatewayPayment::STATUS_PENDING,
                'checkout_order_id' => $orderId,
                'meta' => $meta,
            ],
        );
    }

    public static function attachPpId(string $orderId, string $ppId): void
    {
        $row = PendingGatewayPayment::query()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where('checkout_order_id', $orderId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->first();

        if ($row === null) {
            return;
        }

        $meta = $row->meta ?? [];
        $meta['pp_id'] = $ppId;
        $row->forceFill([
            'transaction_id' => $ppId,
            'meta' => $meta,
        ])->save();
    }

    /**
     * @return array{invoice_id?: int|null, customer_id: int, amount: string, return_to: string, payment_type: string, gateway: string}|null
     */
    public static function resolve(?string $orderId, string $ppId, array $verified): ?array
    {
        if ($orderId !== null && $orderId !== '') {
            $cached = PublicCheckoutSession::get($orderId);
            if ($cached !== null) {
                return $cached;
            }

            $fromDb = static::fromDatabase($orderId, $ppId);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        if ($ppId !== '') {
            $fromDb = static::fromDatabase(null, $ppId);
            if ($fromDb !== null) {
                return $fromDb;
            }
        }

        return static::fromVerifyMetadata($verified);
    }

    public static function markCompleted(string $orderId, int $paymentId): void
    {
        PendingGatewayPayment::query()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where('checkout_order_id', $orderId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->update([
                'status' => PendingGatewayPayment::STATUS_AUTO_APPROVED,
                'payment_id' => $paymentId,
                'reviewed_at' => now(),
            ]);
    }

    /**
     * @return array{invoice_id?: int|null, customer_id: int, amount: string, return_to: string, payment_type: string, gateway: string}|null
     */
    private static function fromDatabase(?string $orderId, string $ppId): ?array
    {
        $query = PendingGatewayPayment::query()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where('status', PendingGatewayPayment::STATUS_PENDING);

        $row = null;
        if ($orderId !== null && $orderId !== '') {
            $row = (clone $query)->where('checkout_order_id', $orderId)->first();
        }
        if ($row === null && $ppId !== '') {
            $row = (clone $query)
                ->where(function ($q) use ($ppId): void {
                    $q->where('transaction_id', $ppId)
                        ->orWhere('meta->pp_id', $ppId);
                })
                ->first();
        }

        if ($row === null) {
            return null;
        }

        $session = $row->meta['checkout_session'] ?? null;
        if (is_array($session) && isset($session['customer_id'], $session['amount'])) {
            return $session;
        }

        return [
            'invoice_id' => $row->invoice_id,
            'customer_id' => (int) $row->customer_id,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'return_to' => (string) ($row->meta['return_to'] ?? 'bill_payment'),
            'payment_type' => (string) ($row->meta['payment_type'] ?? PaymentType::PAYMENT),
            'gateway' => PaymentGateway::PIPRAPAY,
        ];
    }

    /**
     * @param  array<string, mixed>  $verified
     * @return array{invoice_id?: int|null, customer_id: int, amount: string, return_to: string, payment_type: string, gateway: string}|null
     */
    private static function fromVerifyMetadata(array $verified): ?array
    {
        $metadata = $verified['metadata'] ?? ($verified['data']['metadata'] ?? null);
        $decoded = static::decodeMetadata($metadata);
        if ($decoded === null) {
            return null;
        }

        $customerId = (int) ($decoded['customer_id'] ?? 0);
        if ($customerId <= 0) {
            return null;
        }

        $service = PipraPayCheckoutService::fromConfig();
        $amount = $service->verifiedAmount($verified, '0.00');
        if ((float) $amount <= 0) {
            return null;
        }

        return [
            'invoice_id' => isset($decoded['invoice_id']) ? (int) $decoded['invoice_id'] : null,
            'customer_id' => $customerId,
            'amount' => $amount,
            'return_to' => (string) ($decoded['return_to'] ?? 'bill_payment'),
            'payment_type' => (string) ($decoded['payment_type'] ?? PaymentType::PAYMENT),
            'gateway' => PaymentGateway::PIPRAPAY,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeMetadata(mixed $metadata): ?array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (! is_string($metadata) || $metadata === '') {
            return null;
        }

        try {
            $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }
}
