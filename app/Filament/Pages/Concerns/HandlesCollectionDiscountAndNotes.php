<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Invoice;
use App\Services\Billing\CollectionDiscountApplicator;
use App\Services\Billing\CollectionPaymentClassifier;
use App\Services\Billing\CollectionDiscountSettings;
use Illuminate\Validation\ValidationException;

trait HandlesCollectionDiscountAndNotes
{
    public string $collectionDiscountPreset = 'none';

    public string $collectionDiscountCustom = '';

    /**
     * @return array<string, string>
     */
    public function getCollectionDiscountPresetOptions(): array
    {
        if (! CollectionDiscountSettings::userCanApplyDiscount()) {
            return [];
        }

        return ['none' => '— No discount —'] + CollectionDiscountSettings::presetOptions();
    }

    public function canApplyCollectionDiscount(): bool
    {
        return CollectionDiscountSettings::userCanApplyDiscount();
    }

    public function collectionDiscountAllowsCustom(): bool
    {
        return CollectionDiscountSettings::get()['allow_custom_amount'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function collectionDiscountMeta(float $discountBdt): array
    {
        if ($discountBdt <= 0) {
            return [];
        }

        $meta = ['discount' => $discountBdt];
        if ($this->collectionDiscountPreset !== '' && $this->collectionDiscountPreset !== 'none') {
            $meta['collection_discount_preset'] = $this->collectionDiscountPreset;
        }

        return $meta;
    }

    protected function resetCollectionDiscountFields(): void
    {
        $this->collectionDiscountPreset = 'none';
        $this->collectionDiscountCustom = '';
    }

    /**
     * @throws ValidationException
     */
    protected function validateCollectionPayment(?Invoice $invoice, float $payAmount, string $notes): float
    {
        $customer = $this->selectedCustomer !== null
            ? \App\Models\Customer::query()->find($this->selectedCustomerId)
            : null;
        $discountBdt = 0.0;
        $balanceDue = null;

        if ($invoice !== null) {
            $balanceDue = $invoice->balanceDue();
            $isAdvance = CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, $payAmount, 0);
            if ($balanceDue <= 0 && ! $isAdvance) {
                throw ValidationException::withMessages([
                    'invoiceId' => 'This invoice has no balance due.',
                ]);
            }

            if (! $isAdvance && $payAmount > $balanceDue + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount cannot exceed invoice due ('.number_format($balanceDue, 2).' BDT) before discount.',
                ]);
            }

            if ($this->canApplyCollectionDiscount()) {
                $discountBdt = CollectionDiscountSettings::resolveDiscountBdt(
                    $this->collectionDiscountPreset,
                    $this->collectionDiscountCustom,
                    $balanceDue,
                );
            } elseif (
                ($this->collectionDiscountPreset !== '' && $this->collectionDiscountPreset !== 'none')
                || (is_numeric($this->collectionDiscountCustom) && (float) $this->collectionDiscountCustom > 0)
            ) {
                throw ValidationException::withMessages([
                    'collectionDiscountPreset' => 'You do not have permission to apply collection discount.',
                ]);
            }

            if (
                ! CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, $payAmount, $discountBdt)
                && $discountBdt > 0
                && ($payAmount + $discountBdt) > $balanceDue + 0.001
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Cash ('.number_format($payAmount, 2).') + discount ('.number_format($discountBdt, 2).') cannot exceed due '.number_format($balanceDue, 2).' BDT.',
                ]);
            }
        }

        $needsNote = CollectionPaymentClassifier::noteRequired($customer, $invoice, $payAmount, $discountBdt);

        if ($needsNote && trim($notes) === '') {
            $isPartial = $balanceDue !== null && ($payAmount + $discountBdt + 0.001) < $balanceDue;
            throw ValidationException::withMessages([
                'notes' => $isPartial
                    ? 'Partial payment requires a note (কারণ লিখুন — কত নিলেন, বাকি কখন দেবে ইত্যাদি).'
                    : 'Collection discount requires a note explaining why.',
            ]);
        }

        if ($needsNote && mb_strlen(trim($notes)) < 3) {
            throw ValidationException::withMessages([
                'notes' => 'Please enter a meaningful note (at least 3 characters).',
            ]);
        }

        return $discountBdt;
    }

    protected function applyCollectionDiscountIfNeeded(
        ?Invoice $invoice,
        float $discountBdt,
        \App\Models\Payment $payment,
    ): void {
        if ($invoice === null || $discountBdt <= 0) {
            return;
        }

        CollectionDiscountApplicator::apply(
            $invoice,
            $discountBdt,
            $payment,
            $this->collectionDiscountPreset !== 'none' ? $this->collectionDiscountPreset : null,
            $payment->notes,
        );
    }

    public function updatedInvoiceId(mixed $value): void
    {
        if ($value === '' || $value === null || (int) $value === 0) {
            $this->invoiceId = null;

            return;
        }

        $this->invoiceId = (int) $value;
        $this->fillAmountFromSelectedInvoiceDue();
    }

    protected function fillAmountFromSelectedInvoiceDue(): void
    {
        $due = $this->selectedInvoiceBalanceDue();
        if ($due !== null && $due > 0) {
            $this->amount = (string) round($due, 2);
        }
    }

    public function selectedInvoiceBalanceDue(): ?float
    {
        if ($this->invoiceId === null || ! isset($this->selectedCustomer) || $this->selectedCustomer === null) {
            return null;
        }

        foreach ($this->selectedCustomer['invoices'] ?? [] as $inv) {
            if ((int) $inv['id'] === (int) $this->invoiceId) {
                return (float) $inv['balance_due'];
            }
        }

        return null;
    }

    public function partialPaymentRemaining(): ?float
    {
        $due = $this->selectedInvoiceBalanceDue();
        if ($due === null) {
            return null;
        }

        $amount = is_numeric($this->amount) ? (float) $this->amount : 0.0;
        if ($amount <= 0) {
            return $due;
        }

        $discount = $this->previewCollectionDiscountBdt();

        return max(0.0, round($due - $amount - $discount, 2));
    }

    public function previewCollectionDiscountBdt(): float
    {
        $due = $this->selectedInvoiceBalanceDue();
        if ($due === null || ! $this->canApplyCollectionDiscount()) {
            return 0.0;
        }

        return CollectionDiscountSettings::resolveDiscountBdt(
            $this->collectionDiscountPreset,
            $this->collectionDiscountCustom,
            $due,
        );
    }

    public function notesRequiredForCollection(): bool
    {
        $due = $this->selectedInvoiceBalanceDue();
        if ($due === null) {
            return false;
        }

        $settings = CollectionDiscountSettings::get();
        $pay = is_numeric($this->amount) ? (float) $this->amount : 0.0;
        $discount = $this->previewCollectionDiscountBdt();
        $isPartial = $pay > 0 && ($pay + $discount + 0.001) < $due;

        return ($isPartial && $settings['require_note_on_partial'])
            || ($discount > 0 && $settings['require_note_on_discount']);
    }
}
