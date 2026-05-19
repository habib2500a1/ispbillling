<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Support\PaymentGateway;
use Illuminate\Support\Facades\Http;
final class GatewayPaymentVerificationService
{
    /**
     * @param  array<string, mixed>  $session
     * @return array{status: string, payment?: \App\Models\Payment, pending?: PendingGatewayPayment, message: string}
     */
    public function submitRocketConfirmation(
        string $orderId,
        string $transactionId,
        array $session,
    ): array {
        $customerId = (int) ($session['customer_id'] ?? 0);
        $amount = round((float) ($session['amount'] ?? 0), 2);
        $invoiceId = isset($session['invoice_id']) ? (int) $session['invoice_id'] : null;
        $trxId = strtoupper(trim($transactionId));
        $gateway = PaymentGateway::ROCKET;

        if ($this->isDuplicateTransaction($gateway, $trxId)) {
            return [
                'status' => 'duplicate',
                'message' => 'This transaction ID was already used.',
            ];
        }

        $customer = \App\Models\Customer::query()->withoutGlobalScopes()->find($customerId);
        if ($customer === null) {
            return ['status' => 'error', 'message' => 'Customer not found.'];
        }

        $checks = $this->runRocketChecks($trxId, $amount, $session);
        $autoApprove = (bool) config('rocket.auto_verify', false) && ($checks['auto_ok'] ?? false);

        if ($autoApprove) {
            $payment = PaymentProcessor::recordGatewayPayment(
                gateway: $gateway,
                transactionId: $trxId,
                customerId: $customerId,
                invoiceId: $invoiceId,
                amount: $amount,
                reference: 'Rocket '.$trxId,
                meta: [
                    'rocket_order_id' => $orderId,
                    'auto_verified' => true,
                    'confirmed_at' => now()->toIso8601String(),
                ],
            );

            PendingGatewayPayment::query()->create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'gateway' => $gateway,
                'transaction_id' => $trxId,
                'amount' => $amount,
                'status' => PendingGatewayPayment::STATUS_AUTO_APPROVED,
                'checkout_order_id' => $orderId,
                'payment_id' => $payment->id,
                'reviewed_at' => now(),
                'meta' => $checks,
            ]);

            return [
                'status' => 'approved',
                'payment' => $payment,
                'message' => 'Payment verified and recorded. Thank you!',
            ];
        }

        $customerName = $customer->name;
        $pending = PendingGatewayPayment::query()->updateOrCreate(
            ['gateway' => $gateway, 'transaction_id' => $trxId],
            [
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'status' => PendingGatewayPayment::STATUS_PENDING,
                'checkout_order_id' => $orderId,
                'meta' => $checks,
            ],
        );

        if ($pending->wasRecentlyCreated) {
            app(\App\Services\Notifications\OpsAlertNotifier::class)->pendingGatewayPayment(
                (int) $customer->tenant_id,
                $gateway,
                $trxId,
                $amount,
                $customerName,
            );
        }

        return [
            'status' => 'pending',
            'pending' => $pending,
            'message' => 'Payment submitted for verification. You will be notified once approved.',
        ];
    }

    public function approve(PendingGatewayPayment $pending, ?int $reviewerId = null): Payment
    {
        if (in_array($pending->status, [PendingGatewayPayment::STATUS_APPROVED, PendingGatewayPayment::STATUS_AUTO_APPROVED], true)) {
            return $pending->payment ?? Payment::query()->findOrFail($pending->payment_id);
        }

        if ($this->isDuplicateTransaction($pending->gateway, $pending->transaction_id, $pending->id)) {
            throw new \RuntimeException('Transaction ID already used on another payment.');
        }

        $payment = PaymentProcessor::recordGatewayPayment(
            gateway: $pending->gateway,
            transactionId: $pending->transaction_id,
            customerId: $pending->customer_id,
            invoiceId: $pending->invoice_id,
            amount: (float) $pending->amount,
            reference: strtoupper($pending->gateway).' '.$pending->transaction_id,
            meta: ['pending_id' => $pending->id, 'approved_at' => now()->toIso8601String()],
        );

        $pending->forceFill([
            'status' => PendingGatewayPayment::STATUS_APPROVED,
            'payment_id' => $payment->id,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        return $payment;
    }

    public function reject(PendingGatewayPayment $pending, ?string $reason = null, ?int $reviewerId = null): void
    {
        $pending->forceFill([
            'status' => PendingGatewayPayment::STATUS_REJECTED,
            'reject_reason' => $reason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array{auto_ok: bool, trx_format_ok: bool, amount_ok: bool, remote_ok: bool|null, reasons: list<string>}
     */
    public function runRocketChecks(string $trxId, float $amount, array $session): array
    {
        $reasons = [];
        $minLen = max(4, (int) config('rocket.trx_id_min_length', 8));
        $trxFormatOk = strlen($trxId) >= $minLen && (bool) preg_match('/^[A-Z0-9]+$/', $trxId);
        if (! $trxFormatOk) {
            $reasons[] = 'invalid_trx_format';
        }

        $sessionAmount = round((float) ($session['amount'] ?? 0), 2);
        $tolerance = (float) config('rocket.amount_match_tolerance', 0.01);
        $amountOk = abs($sessionAmount - $amount) <= $tolerance;
        if (! $amountOk) {
            $reasons[] = 'amount_mismatch';
        }

        $remoteOk = $this->verifyViaRemoteEndpoint($trxId, $amount);
        if ($remoteOk === false) {
            $reasons[] = 'remote_verify_failed';
        }

        $requireRemote = filled(config('rocket.verify_url'));
        $autoOk = $trxFormatOk && $amountOk && (! $requireRemote || $remoteOk === true);

        return [
            'auto_ok' => $autoOk,
            'trx_format_ok' => $trxFormatOk,
            'amount_ok' => $amountOk,
            'remote_ok' => $remoteOk,
            'reasons' => $reasons,
        ];
    }

    private function verifyViaRemoteEndpoint(string $trxId, float $amount): ?bool
    {
        $url = trim((string) config('rocket.verify_url', ''));
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post($url, [
                    'transaction_id' => $trxId,
                    'amount' => $amount,
                    'secret' => config('rocket.verify_secret'),
                    'gateway' => 'rocket',
                ]);

            if (! $response->successful()) {
                return false;
            }

            $json = $response->json();

            return (bool) ($json['verified'] ?? $json['success'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isDuplicateTransaction(string $gateway, string $trxId, ?int $ignorePendingId = null): bool
    {
        if (Payment::query()
            ->where('method', $gateway)
            ->where('gateway_transaction_id', $trxId)
            ->exists()) {
            return true;
        }

        $pendingQuery = PendingGatewayPayment::query()
            ->where('gateway', $gateway)
            ->where('transaction_id', $trxId)
            ->whereIn('status', [
                PendingGatewayPayment::STATUS_PENDING,
                PendingGatewayPayment::STATUS_APPROVED,
                PendingGatewayPayment::STATUS_AUTO_APPROVED,
            ]);

        if ($ignorePendingId !== null) {
            $pendingQuery->where('id', '!=', $ignorePendingId);
        }

        return $pendingQuery->exists();
    }
}
