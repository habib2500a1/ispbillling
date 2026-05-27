<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\MfsSmsRecord;
use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Support\CheckoutPaymentMeta;
use App\Support\PaymentType;
use App\Support\PersonalMfsGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;

final class GatewayPaymentVerificationService
{
    /**
     * @param  array<string, mixed>  $session
     * @return array{status: string, payment?: Payment, pending?: PendingGatewayPayment, message: string}
     */
    public function submitRocketConfirmation(
        string $orderId,
        string $transactionId,
        array $session,
    ): array {
        return $this->submitPersonalConfirmation(
            \App\Support\PaymentGateway::ROCKET,
            $orderId,
            $transactionId,
            $session,
        );
    }

    /**
     * Personal MFS (bKash / Nagad / Rocket): customer pays personal number, enters TrxID.
     * Auto-approve when SMS ledger matches (PipraPay-style) or queue for admin.
     *
     * @param  array<string, mixed>  $session
     * @return array{status: string, payment?: Payment, pending?: PendingGatewayPayment, message: string}
     */
    public function submitPersonalConfirmation(
        string $gateway,
        string $orderId,
        string $transactionId,
        array $session,
    ): array {
        $gateway = strtolower(trim($gateway));
        $customerId = (int) ($session['customer_id'] ?? 0);
        $amount = round((float) ($session['amount'] ?? 0), 2);
        $invoiceId = isset($session['invoice_id']) ? (int) $session['invoice_id'] : null;
        $trxId = PersonalMfsGateway::normalizeTrxId($transactionId);

        if ($this->isDuplicateTransaction($gateway, $trxId)) {
            return [
                'status' => 'duplicate',
                'message' => 'This transaction ID was already used.',
            ];
        }

        $customer = Customer::query()->withoutGlobalScopes()->find($customerId);
        if ($customer === null) {
            return ['status' => 'error', 'message' => 'Customer not found.'];
        }

        $checks = $this->runPersonalChecks($gateway, $trxId, $amount, $session, (int) $customer->tenant_id);
        $autoApprove = PersonalMfsGateway::autoVerifyEnabled($gateway) && ($checks['auto_ok'] ?? false);

        if ($autoApprove) {
            return DB::transaction(function () use ($gateway, $orderId, $trxId, $customer, $customerId, $invoiceId, $amount, $checks, $session): array {
                return $this->finalizeAutoApproval(
                    gateway: $gateway,
                    orderId: $orderId,
                    trxId: $trxId,
                    tenantId: (int) $customer->tenant_id,
                    customerId: $customerId,
                    invoiceId: $invoiceId,
                    amount: $amount,
                    checks: $checks,
                    checkoutSession: $session,
                );
            });
        }

        $pending = PendingGatewayPayment::query()->updateOrCreate(
            ['gateway' => $gateway, 'transaction_id' => $trxId],
            [
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'status' => PendingGatewayPayment::STATUS_PENDING,
                'checkout_order_id' => $orderId,
                'meta' => array_merge($checks, [
                    'payment_type' => $session['payment_type'] ?? PaymentType::PAYMENT,
                    'prepay_months' => $session['prepay_months'] ?? null,
                    'return_to' => $session['return_to'] ?? null,
                ]),
            ],
        );

        if ($pending->wasRecentlyCreated) {
            app(\App\Services\Notifications\OpsAlertNotifier::class)->pendingGatewayPayment(
                (int) $customer->tenant_id,
                $gateway,
                $trxId,
                $amount,
                $customer->name,
            );
        }

        // SMS may have landed in the ledger just before/after this submit (late scan, race).
        $lateMatch = $this->tryAutoApprovePending($pending->fresh() ?? $pending);
        if (($lateMatch['status'] ?? '') === 'approved') {
            return [
                'status' => 'approved',
                'payment' => $lateMatch['payment'] ?? null,
                'message' => $lateMatch['message'] ?? 'Payment verified and recorded. Thank you!',
            ];
        }

        $reasonText = PersonalMfsGateway::pendingReasonMessage($checks['reasons'] ?? []);
        $baseMessage = $reasonText !== 'Waiting for admin approval or SMS match.'
            ? 'Could not auto-verify: '.$reasonText
            : 'Payment submitted for verification. You will be notified once approved.';

        return [
            'status' => 'pending',
            'pending' => $pending,
            'message' => $baseMessage,
            'merchant_number' => PersonalMfsGateway::merchantNumber($gateway),
            'customer_notice' => PersonalMfsGateway::customerPendingNotice($gateway, ['message' => $baseMessage]),
            'checks' => $checks,
        ];
    }

    /**
     * Re-check SMS ledger for a pending row (customer submitted TrxID before SMS arrived).
     *
     * @return array{status: string, payment?: Payment, message: string}
     */
    public function tryAutoApprovePending(PendingGatewayPayment $pending): array
    {
        if ($pending->status !== PendingGatewayPayment::STATUS_PENDING) {
            return ['status' => 'skipped', 'message' => 'Not pending.'];
        }

        if ($pending->customer_id === null) {
            return ['status' => 'pending', 'message' => 'Assign subscriber ID first (SMS had no matching ID/phone).'];
        }

        if (! PersonalMfsGateway::autoVerifyEnabled($pending->gateway)) {
            return ['status' => 'skipped', 'message' => 'Auto-verify is off for this gateway.'];
        }

        $checks = $this->runPersonalChecks(
            $pending->gateway,
            $pending->transaction_id,
            (float) $pending->amount,
            [
                'amount' => (float) $pending->amount,
                'customer_id' => $pending->customer_id,
                'invoice_id' => $pending->invoice_id,
            ],
            (int) $pending->tenant_id,
        );

        if (! ($checks['auto_ok'] ?? false)) {
            return [
                'status' => 'pending',
                'message' => PersonalMfsGateway::pendingReasonMessage($checks['reasons'] ?? []),
            ];
        }

        return DB::transaction(function () use ($pending, $checks): array {
            $locked = PendingGatewayPayment::query()->whereKey($pending->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== PendingGatewayPayment::STATUS_PENDING) {
                return ['status' => 'skipped', 'message' => 'Already processed.'];
            }

            return $this->finalizeAutoApproval(
                gateway: $locked->gateway,
                orderId: (string) ($locked->checkout_order_id ?? ''),
                trxId: $locked->transaction_id,
                tenantId: (int) $locked->tenant_id,
                customerId: (int) $locked->customer_id,
                invoiceId: $locked->invoice_id,
                amount: (float) $locked->amount,
                checks: $checks,
                existingPending: $locked,
            );
        });
    }

    public function isDuplicateGatewayTransaction(string $gateway, string $trxId): bool
    {
        return $this->isDuplicateTransaction($gateway, $trxId);
    }

    /**
     * When a new SMS lands in the ledger, auto-complete matching pending TrxID rows.
     * SMS Ref/Counter wins over the portal checkout customer (logged-in wrong ID).
     */
    public function matchPendingForSms(MfsSmsRecord $sms): int
    {
        if ($sms->status !== MfsSmsRecord::STATUS_APPROVED) {
            return 0;
        }

        $pendings = PendingGatewayPayment::query()
            ->where('tenant_id', $sms->tenant_id)
            ->where('gateway', $sms->gateway)
            ->where('transaction_id', $sms->transaction_id)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        if ($pendings->isEmpty()) {
            return 0;
        }

        $explicitRef = isset($sms->meta['customer_reference'])
            ? (string) $sms->meta['customer_reference']
            : null;
        $resolved = app(MfsCustomerReferenceMatcher::class)->resolve(
            (int) $sms->tenant_id,
            (string) ($sms->raw_message ?? ''),
            $explicitRef !== '' ? $explicitRef : null,
            $sms->transaction_id,
            $sms->sender_phone,
        );

        $matched = 0;
        foreach ($pendings as $pending) {
            $customerId = $this->customerIdForSmsPendingMatch($pending, $resolved);
            if ($customerId === null) {
                continue;
            }

            if ((int) ($pending->customer_id ?? 0) !== $customerId) {
                $pending->forceFill([
                    'customer_id' => $customerId,
                    'meta' => array_merge($pending->meta ?? [], [
                        'matched_by' => $resolved['matched_by'],
                        'reference_token' => $resolved['token'],
                        'sms_reference_override' => true,
                        'previous_customer_id' => $pending->customer_id,
                    ]),
                ])->save();
            }

            $result = $this->tryAutoApprovePending($pending->fresh() ?? $pending);
            if (($result['status'] ?? '') === 'approved') {
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * @param  array{customer: ?Customer, customers: list<Customer>, token: ?string, matched_by: ?string, candidates: list<string>}  $resolved
     */
    private function customerIdForSmsPendingMatch(PendingGatewayPayment $pending, array $resolved): ?int
    {
        $matchedBy = (string) ($resolved['matched_by'] ?? '');

        if (str_starts_with($matchedBy, 'sms_reference')) {
            return $resolved['customer']?->id;
        }

        if ($resolved['customer'] !== null) {
            return (int) $resolved['customer']->id;
        }

        return $pending->customer_id !== null ? (int) $pending->customer_id : null;
    }

    /**
     * Retry all pending rows against the SMS ledger (customer submitted TrxID before SMS, or APK already sent SMS once).
     */
    public function retryAllPendingMatches(?int $tenantId = null, int $limit = 150): int
    {
        if (! (bool) config('mfs_personal.sms_ingest.enabled', false)) {
            return 0;
        }

        $query = PendingGatewayPayment::query()
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->orderBy('id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $matched = 0;
        foreach ($query->limit($limit)->get() as $pending) {
            $result = $this->tryAutoApprovePending($pending);
            if (($result['status'] ?? '') === 'approved') {
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * Admin picks subscriber ID for an unmatched SMS payment, then records payment (FIFO bills + advance).
     */
    public function assignAndApprove(
        PendingGatewayPayment $pending,
        int $customerId,
        ?int $invoiceId = null,
        ?int $reviewerId = null,
    ): Payment {
        if ($pending->status !== PendingGatewayPayment::STATUS_PENDING) {
            throw new \RuntimeException('Only pending payments can be assigned.');
        }

        if ($pending->customer_id !== null && (int) $pending->customer_id !== $customerId) {
            throw new \RuntimeException('Payment is already linked to another subscriber.');
        }

        $pending->forceFill([
            'customer_id' => $customerId,
            'invoice_id' => $invoiceId,
            'meta' => array_merge($pending->meta ?? [], [
                'matched_by' => 'admin_assigned',
                'fifo_multi_invoice' => true,
                'assigned_by' => $reviewerId,
                'assigned_at' => now()->toIso8601String(),
            ]),
        ])->save();

        return $this->approve($pending->fresh() ?? $pending, $reviewerId);
    }

    public function approve(PendingGatewayPayment $pending, ?int $reviewerId = null): Payment
    {
        if (in_array($pending->status, [PendingGatewayPayment::STATUS_APPROVED, PendingGatewayPayment::STATUS_AUTO_APPROVED], true)) {
            return $pending->payment ?? Payment::query()->findOrFail($pending->payment_id);
        }

        if ($pending->customer_id === null) {
            throw new \RuntimeException('Select a subscriber ID before approving this payment.');
        }

        if ($this->isDuplicateTransaction($pending->gateway, $pending->transaction_id, $pending->id)) {
            throw new \RuntimeException('Transaction ID already used on another payment.');
        }

        return DB::transaction(function () use ($pending, $reviewerId): Payment {
            $locked = PendingGatewayPayment::query()->whereKey($pending->id)->lockForUpdate()->firstOrFail();

            $paymentMeta = [
                'pending_id' => $locked->id,
                'approved_at' => now()->toIso8601String(),
                'matched_by' => $locked->meta['matched_by'] ?? 'manual',
            ];
            if (! empty($locked->meta['fifo_multi_invoice'])) {
                $paymentMeta['fifo_multi_invoice'] = true;
            }

            $payment = PaymentProcessor::recordGatewayPayment(
                gateway: $locked->gateway,
                transactionId: $locked->transaction_id,
                customerId: (int) $locked->customer_id,
                invoiceId: $locked->invoice_id,
                amount: (float) $locked->amount,
                reference: strtoupper($locked->gateway).' '.$locked->transaction_id,
                meta: $paymentMeta,
            );

            $locked->forceFill([
                'status' => PendingGatewayPayment::STATUS_APPROVED,
                'payment_id' => $payment->id,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ])->save();

            $smsId = $locked->meta['sms_record_id'] ?? null;
            if ($smsId !== null) {
                $sms = MfsSmsRecord::query()->find($smsId);
                if ($sms !== null && $sms->status !== MfsSmsRecord::STATUS_USED) {
                    app(MfsSmsMatchingService::class)->markUsed($sms, (int) $locked->id, (int) $payment->id);
                    $payment->loadMissing('customer');
                    $sms->fresh()?->enrichMatchedCustomerMeta($payment->customer);
                }
            }

            return $payment;
        });
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
     * @return array{auto_ok: bool, trx_format_ok: bool, amount_ok: bool, sms_matched: bool, sms_record_id: ?int, remote_ok: bool|null, reasons: list<string>}
     */
    public function runPersonalChecks(string $gateway, string $trxId, float $amount, array $session, int $tenantId): array
    {
        $reasons = [];
        $format = PersonalMfsGateway::validateTrxFormat($gateway, $trxId);
        $trxFormatOk = $format['ok'];
        if (! $trxFormatOk) {
            $reasons = array_merge($reasons, $format['reasons']);
        }

        $sessionAmount = round((float) ($session['amount'] ?? 0), 2);
        $tolerance = (float) config('mfs_personal.amount_tolerance', 0.01);
        $amountOk = abs($sessionAmount - $amount) <= $tolerance;
        if (! $amountOk) {
            $reasons[] = 'amount_mismatch';
        }

        $smsMatch = app(MfsSmsMatchingService::class)->matchForPayment($tenantId, $gateway, $trxId, $amount);
        $smsMatched = $smsMatch['matched'];
        $smsRecordId = $smsMatch['sms']?->id;
        if (! $smsMatched && (bool) config('mfs_personal.sms_ingest.enabled', false)) {
            $reasons = array_merge($reasons, $smsMatch['reasons']);
        }

        $remoteOk = $this->verifyViaRemoteEndpoint($gateway, $trxId, $amount);
        if ($remoteOk === false) {
            $reasons[] = 'remote_verify_failed';
        }

        $requireSms = (bool) config('mfs_personal.sms_ingest.enabled', false);
        $requireRemote = filled(config('rocket.verify_url')) && $gateway === \App\Support\PaymentGateway::ROCKET;

        $autoOk = $trxFormatOk && $amountOk
            && (! $requireSms || $smsMatched)
            && (! $requireRemote || $remoteOk === true);

        return [
            'auto_ok' => $autoOk,
            'trx_format_ok' => $trxFormatOk,
            'amount_ok' => $amountOk,
            'sms_matched' => $smsMatched,
            'sms_record_id' => $smsRecordId,
            'remote_ok' => $remoteOk,
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $checks
     * @return array{status: string, payment?: Payment, message: string}
     */
    public function finalizeAutoApproval(
        string $gateway,
        string $orderId,
        string $trxId,
        int $tenantId,
        int $customerId,
        ?int $invoiceId,
        float $amount,
        array $checks,
        ?PendingGatewayPayment $existingPending = null,
        ?array $checkoutSession = null,
    ): array {
        return DB::transaction(function () use (
            $gateway,
            $orderId,
            $trxId,
            $tenantId,
            $customerId,
            $invoiceId,
            $amount,
            $checks,
            $existingPending,
            $checkoutSession,
        ): array {
            if ($this->isDuplicateTransaction($gateway, $trxId)) {
                $existing = Payment::query()
                    ->withoutGlobalScopes()
                    ->where('gateway', $gateway)
                    ->where('gateway_transaction_id', $trxId)
                    ->first();

                if ($existing !== null) {
                    return [
                        'status' => 'approved',
                        'payment' => $existing,
                        'message' => 'Payment verified and recorded. Thank you!',
                    ];
                }
            }

            $sms = null;
            if (($checks['sms_record_id'] ?? null) !== null) {
                $sms = MfsSmsRecord::query()
                    ->whereKey($checks['sms_record_id'])
                    ->lockForUpdate()
                    ->first();

                if ($sms !== null && $sms->status === MfsSmsRecord::STATUS_USED) {
                    $existing = Payment::query()
                        ->withoutGlobalScopes()
                        ->where('gateway', $gateway)
                        ->where('gateway_transaction_id', $trxId)
                        ->first();

                    if ($existing !== null) {
                        return [
                            'status' => 'approved',
                            'payment' => $existing,
                            'message' => 'Payment verified and recorded. Thank you!',
                        ];
                    }
                }
            }

            $session = $checkoutSession ?? PublicCheckoutSession::get($orderId) ?? [];
            $paymentType = (string) ($session['payment_type'] ?? PaymentType::PAYMENT);

            $payment = PaymentProcessor::recordGatewayPayment(
                gateway: $gateway,
                transactionId: $trxId,
                customerId: $customerId,
                invoiceId: $invoiceId,
                amount: $amount,
                reference: strtoupper($gateway).' '.$trxId,
                meta: CheckoutPaymentMeta::fromSession($session, [
                    'checkout_order_id' => $orderId,
                    'auto_verified' => true,
                    'verification' => $checks,
                    'confirmed_at' => now()->toIso8601String(),
                    'fifo_multi_invoice' => (bool) ($checks['fifo_multi_invoice'] ?? false),
                    'matched_by' => $checks['matched_by'] ?? null,
                    'reference_token' => $checks['reference_token'] ?? null,
                ]),
                paymentType: $paymentType,
            );

            if ($existingPending !== null) {
                $existingPending->forceFill([
                    'status' => PendingGatewayPayment::STATUS_AUTO_APPROVED,
                    'payment_id' => $payment->id,
                    'reviewed_at' => now(),
                    'meta' => array_merge($existingPending->meta ?? [], $checks, ['auto_matched_late' => true]),
                ])->save();
                $pending = $existingPending;
            } else {
            $payload = [
                'tenant_id' => $tenantId,
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
            ];

            // Pending rows are unique per (gateway, transaction_id). If an earlier ingest created
            // an "unmatched SMS" pending row (customer_id = null), we must update it instead
            // of trying to insert a second pending record.
            try {
                $pending = PendingGatewayPayment::query()
                    ->where('gateway', $gateway)
                    ->where('transaction_id', $trxId)
                    ->lockForUpdate()
                    ->first();

                if ($pending !== null) {
                    $pending->forceFill($payload)->save();
                } else {
                    $pending = PendingGatewayPayment::query()->create($payload);
                }
            } catch (QueryException $e) {
                $pending = PendingGatewayPayment::query()
                    ->where('gateway', $gateway)
                    ->where('transaction_id', $trxId)
                    ->first();

                if ($pending === null) {
                    throw $e;
                }

                $pending->forceFill($payload)->save();
            }
            }

            if ($sms !== null && $sms->status !== MfsSmsRecord::STATUS_USED) {
                app(MfsSmsMatchingService::class)->markUsed($sms, (int) $pending->id, (int) $payment->id);
            }

            return [
                'status' => 'approved',
                'payment' => $payment,
                'message' => 'Payment verified and recorded. Thank you!',
            ];
        });
    }

    private function verifyViaRemoteEndpoint(string $gateway, string $trxId, float $amount): ?bool
    {
        $url = trim((string) config('rocket.verify_url', ''));
        if ($url === '' || $gateway !== \App\Support\PaymentGateway::ROCKET) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post($url, [
                    'transaction_id' => $trxId,
                    'amount' => $amount,
                    'secret' => config('rocket.verify_secret'),
                    'gateway' => $gateway,
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
            ->withoutGlobalScopes()
            ->where('method', $gateway)
            ->where('gateway_transaction_id', $trxId)
            ->exists()) {
            return true;
        }

        $pendingQuery = PendingGatewayPayment::query()
            ->withoutGlobalScopes()
            ->where('gateway', $gateway)
            ->where('transaction_id', $trxId)
            ->where(function ($query): void {
                $query->whereIn('status', [
                    PendingGatewayPayment::STATUS_APPROVED,
                    PendingGatewayPayment::STATUS_AUTO_APPROVED,
                ])->orWhere(function ($query): void {
                    // Unmatched SMS queue rows have no customer — portal checkout may claim them.
                    $query->where('status', PendingGatewayPayment::STATUS_PENDING)
                        ->whereNotNull('customer_id');
                });
            });

        if ($ignorePendingId !== null) {
            $pendingQuery->where('id', '!=', $ignorePendingId);
        }

        return $pendingQuery->exists();
    }
}
