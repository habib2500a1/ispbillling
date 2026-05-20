<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\CollectionPaymentClassifier;
use Tests\TestCase;

class CollectionPaymentClassifierTest extends TestCase
{
    public function test_overpayment_is_advance_without_note(): void
    {
        $customer = new Customer(['billing_mode' => 'postpaid']);
        $invoice = new Invoice([
            'total' => 500,
            'amount_paid' => 0,
            'discount_amount' => 0,
        ]);

        $this->assertTrue(CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, 600, 0));
        $this->assertFalse(CollectionPaymentClassifier::noteRequired($customer, $invoice, 600, 0));
    }

    public function test_partial_payment_still_needs_note_when_configured(): void
    {
        config([
            'billing.collection_discount' => [
                'enabled' => true,
                'require_note_on_partial' => true,
                'require_note_on_discount' => true,
            ],
        ]);

        $customer = new Customer(['billing_mode' => 'postpaid']);
        $invoice = new Invoice([
            'total' => 500,
            'amount_paid' => 0,
            'discount_amount' => 0,
        ]);

        $this->assertFalse(CollectionPaymentClassifier::isAdvanceCollection($customer, $invoice, 200, 0));
        $this->assertTrue(CollectionPaymentClassifier::noteRequired($customer, $invoice, 200, 0));
    }
}
