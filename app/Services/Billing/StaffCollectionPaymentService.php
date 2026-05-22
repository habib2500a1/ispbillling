<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Collector\CollectorVisitService;
use App\Services\Billing\BillingDueRealtimeSync;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Validation\ValidationException;

final class StaffCollectionPaymentService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{payment: Payment, visit_id: int|null, discount_bdt: float, message: string, customer: array<string, mixed>}
     */
    public function record(User $user, Customer $customer, array $data, string $source = 'mobile-api'): array
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $presetId = (string) ($data['discount_preset'] ?? 'none');
        $customDiscount = (string) ($data['discount_custom'] ?? '');
        $notes = trim((string) ($data['notes'] ?? ''));
        $invoiceId = isset($data['invoice_id']) ? (int) $data['invoice_id'] : null;

        $invoice = OpenInvoiceResolver::forCustomer($customer, $invoiceId);
        if ($invoice === null && $invoiceId) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice not found for this customer.']);
        }
        if ($invoice === null && $amount > 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'No open bill with balance due for this customer.',
            ]);
        }

        if ($amount > 0) {
            $duplicate = $this->findRecentDuplicatePayment($user, $customer, $invoice?->id, $amount);
            if ($duplicate !== null) {
                $due = BillingDueRealtimeSync::afterPayment($customer, queueNetwork: true);

                return [
                    'payment' => $duplicate->fresh(),
                    'visit_id' => null,
                    'discount_bdt' => (float) ($duplicate->meta['discount'] ?? 0),
                    'message' => 'Payment already recorded (duplicate submit ignored).',
                    'customer' => array_merge(
                        BillingDueRealtimeSync::customerPayload($customer),
                        ['balance_due' => $due],
                    ),
                ];
            }
        }

        $discountBdt = $this->validateAndResolveDiscount($user, $customer, $invoice, $amount, $presetId, $customDiscount, $notes);

        if ($amount <= 0 && $discountBdt <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter cash amount or apply a discount.',
            ]);
        }

        $meta = CollectionPaymentClassifier::paymentMeta(
            $customer,
            $invoice,
            $amount,
            $discountBdt,
            $discountBdt > 0 ? ['discount' => $discountBdt] : [],
        );

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'payment_type' => PaymentType::PAYMENT,
            'amount' => $amount,
            'method' => (string) ($data['method'] ?? PaymentGateway::CASH),
            'reference' => $data['reference'] ?? null,
            'notes' => $notes !== '' ? $notes : null,
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'meta' => $meta,
        ]);

        if ($discountBdt > 0 && $invoice !== null) {
            CollectionDiscountApplicator::apply(
                $invoice,
                $discountBdt,
                $payment,
                $presetId !== 'none' ? $presetId : null,
                $notes,
            );
        }

        $visit = app(CollectorVisitService::class)->recordCollection($user, $customer, $payment, [
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'location_text' => $data['location_text'] ?? null,
            'notes' => $notes !== '' ? $notes : null,
            'device_meta' => ['source' => $source],
        ]);

        $message = CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, $amount, $discountBdt)
            ? 'Advance recorded.'
            : 'Payment recorded.';
        if ($discountBdt > 0) {
            $message .= ' Discount '.number_format($discountBdt, 2).' BDT.';
        }

        $due = BillingDueRealtimeSync::afterPayment($customer, queueNetwork: true);

        return [
            'payment' => $payment->fresh(),
            'visit_id' => $visit->id,
            'discount_bdt' => $discountBdt,
            'message' => $message,
            'customer' => array_merge(
                BillingDueRealtimeSync::customerPayload($customer),
                ['balance_due' => $due],
            ),
        ];
    }

    protected function validateAndResolveDiscount(
        User $user,
        Customer $customer,
        ?Invoice $invoice,
        float $payAmount,
        string $presetId,
        string $customDiscount,
        string $notes,
    ): float {
        $settings = CollectionDiscountSettings::get();
        $discountBdt = 0.0;
        $balanceDue = null;
        $isAdvance = CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, $payAmount, 0);

        if ($invoice !== null) {
            $balanceDue = $invoice->balanceDue();
            if ($balanceDue <= 0 && ! $isAdvance) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'This invoice has no balance due.',
                ]);
            }

            if (! $isAdvance && $payAmount > $balanceDue + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount cannot exceed due ('.number_format($balanceDue, 2).' BDT) before discount.',
                ]);
            }

            if (CollectionDiscountSettings::userCanApplyDiscount($user)) {
                $discountBdt = CollectionDiscountSettings::resolveDiscountBdt(
                    $presetId,
                    $customDiscount,
                    $balanceDue,
                    $user,
                );
            } elseif (
                ($presetId !== '' && $presetId !== 'none')
                || (is_numeric($customDiscount) && (float) $customDiscount > 0)
            ) {
                throw ValidationException::withMessages([
                    'discount_preset' => 'You do not have permission to apply collection discount.',
                ]);
            }

            if (
                ! CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, $payAmount, $discountBdt)
                && $discountBdt > 0
                && ($payAmount + $discountBdt) > $balanceDue + 0.001
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Cash + discount cannot exceed due '.number_format($balanceDue, 2).' BDT.',
                ]);
            }
        }

        $needsNote = CollectionPaymentClassifier::noteRequired($customer, $invoice, $payAmount, $discountBdt);
        $isPartial = $invoice !== null
            && $balanceDue !== null
            && ($payAmount + $discountBdt) < $balanceDue - 0.001;

        if ($needsNote && $notes === '') {
            throw ValidationException::withMessages([
                'notes' => $isPartial
                    ? 'Partial payment requires a note at the bottom (কত নিলেন, বাকি কখন).'
                    : 'Discount requires a note explaining why.',
            ]);
        }

        if ($needsNote && mb_strlen($notes) < 3) {
            throw ValidationException::withMessages([
                'notes' => 'Please enter a meaningful note (at least 3 characters).',
            ]);
        }

        return $discountBdt;
    }

    protected function findRecentDuplicatePayment(User $user, Customer $customer, ?int $invoiceId, float $amount): ?Payment
    {
        return Payment::withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where('payment_type', PaymentType::PAYMENT)
            ->where('status', 'completed')
            ->where('recorded_by', $user->id)
            ->where('amount', round($amount, 2))
            ->when($invoiceId !== null, fn ($q) => $q->where('invoice_id', $invoiceId))
            ->where('created_at', '>=', now()->subSeconds(45))
            ->latest('id')
            ->first();
    }
}
