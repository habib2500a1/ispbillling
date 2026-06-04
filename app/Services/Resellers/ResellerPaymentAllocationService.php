<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\OpenInvoiceResolver;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerPaymentAllocationService
{
    public const MODE_SINGLE = 'single';

    public const MODE_FIFO = 'fifo';

    public const MODE_ADVANCE = 'advance';

    /**
     * @param  array<string, mixed>  $data
     * @return array{payment: Payment, allocations: list<array{invoice_id: int, invoice_number: string, amount: float}>, message: string}
     */
    public function recordFifo(
        User $user,
        Customer $customer,
        array $data,
        string $source = 'reseller-portal',
    ): array {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        }

        $open = OpenInvoiceResolver::openInvoicesWithBalance($customer);
        if ($open->isEmpty()) {
            return $this->recordAdvanceWallet($user, $customer, $data, $source);
        }

        return DB::transaction(function () use ($user, $customer, $data, $source, $amount, $open): array {
            $remaining = $amount;
            $allocations = [];

            foreach ($open as $invoice) {
                if ($remaining <= 0.009) {
                    break;
                }

                $due = $invoice->balanceDue();
                $apply = round(min($remaining, $due), 2);
                if ($apply <= 0) {
                    continue;
                }

                $allocations[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'amount' => $apply,
                ];

                $remaining = round($remaining - $apply, 2);
            }

            $primaryInvoiceId = $allocations[0]['invoice_id'] ?? null;

            $payment = Payment::createTrusted([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'invoice_id' => $primaryInvoiceId,
                'payment_type' => PaymentType::PAYMENT,
                'amount' => $amount,
                'method' => (string) ($data['method'] ?? PaymentGateway::CASH),
                'reference' => $data['reference'] ?? null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'status' => 'completed',
                'paid_at' => now(),
                'recorded_by' => $user->id,
                'meta' => [
                    'source' => $source,
                    'allocation_mode' => self::MODE_FIFO,
                    'fifo_allocations' => $allocations,
                    'wallet_surplus' => max(0, $remaining),
                ],
            ]);

            $message = count($allocations) > 1
                ? sprintf(
                    'Payment split across %d bill(s): %s BDT.',
                    count($allocations),
                    number_format($amount, 2),
                )
                : 'Payment recorded.';

            if ($remaining > 0.009) {
                $message .= ' '.number_format($remaining, 2).' BDT added to customer advance balance.';
            }

            return [
                'payment' => $payment->fresh(),
                'allocations' => $allocations,
                'message' => $message,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{payment: Payment, allocations: list<array>, message: string}
     */
    public function recordAdvanceWallet(
        User $user,
        Customer $customer,
        array $data,
        string $source = 'reseller-portal',
    ): array {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        }

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => null,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => $amount,
            'method' => (string) ($data['method'] ?? PaymentGateway::CASH),
            'reference' => $data['reference'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'meta' => [
                'source' => $source,
                'allocation_mode' => self::MODE_ADVANCE,
            ],
        ]);

        return [
            'payment' => $payment->fresh(),
            'allocations' => [],
            'message' => 'Advance payment recorded — credited to customer wallet (no open bills).',
        ];
    }

    /**
     * Apply FIFO rows stored on payment meta (called from PaymentProcessor).
     *
     * @param  list<array{invoice_id: int, amount: float}>  $allocations
     */
    public function applyFifoAllocations(Payment $payment, Customer $customer, array $allocations): float
    {
        $surplus = (float) ($payment->meta['wallet_surplus'] ?? 0);

        foreach ($allocations as $row) {
            $invoice = Invoice::withoutGlobalScopes()->find($row['invoice_id'] ?? 0);
            if ($invoice === null || (int) $invoice->customer_id !== (int) $customer->id) {
                continue;
            }

            $apply = round((float) ($row['amount'] ?? 0), 2);
            if ($apply <= 0) {
                continue;
            }

            $invoice->forceFill([
                'amount_paid' => round((float) $invoice->amount_paid + $apply, 2),
            ])->save();
            InvoiceCalculator::recalculate($invoice->fresh());
        }

        return max(0, $surplus);
    }
}
