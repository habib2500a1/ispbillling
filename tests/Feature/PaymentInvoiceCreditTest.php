<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentInvoiceCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_payment_increments_invoice_amount_paid(): void
    {
        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Pay Customer',
            'phone' => '01600000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 800,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 800,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 300,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $invoice->refresh();

        $this->assertSame(300.0, (float) $invoice->amount_paid);
        $this->assertSame('partial', $invoice->status);
    }

    public function test_pending_payment_does_not_credit_invoice(): void
    {
        $package = Package::query()->create([
            'name' => 'Plan2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 400,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'C2',
            'phone' => '01600000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 400,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 400,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        Payment::query()->create([
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 400,
            'method' => 'bkash',
            'status' => 'pending',
        ]);

        $invoice->refresh();

        $this->assertSame(0.0, (float) $invoice->amount_paid);
    }
}
