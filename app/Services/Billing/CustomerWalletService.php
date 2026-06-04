<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\TenantResolver;

final class CustomerWalletService
{
    public function deposit(Customer $customer, float $amount, ?string $notes = null, ?int $recordedBy = null): Payment
    {
        $amount = round(max(0.01, $amount), 2);

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id ?? TenantResolver::requiredTenantId(),
            'customer_id' => $customer->id,
            'payment_type' => PaymentType::WALLET_DEPOSIT,
            'amount' => $amount,
            'method' => PaymentGateway::OTHER,
            'reference' => 'wallet-recharge-'.now()->format('YmdHis'),
            'notes' => $notes ?? 'Wallet recharge from admin',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $recordedBy ?? auth()->id(),
            'meta' => ['source' => 'admin_wallet_recharge'],
        ]);

        PaymentProcessor::processCompletedPayment($payment);

        return $payment->fresh() ?? $payment;
    }
}
