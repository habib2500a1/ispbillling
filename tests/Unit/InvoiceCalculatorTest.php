<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Services\Billing\InvoiceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculates_subtotal_tax_and_total_from_line_items(): void
    {
        $package = Package::query()->create([
            'name' => '10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 5,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'phone' => '01700000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => 100,
            'total' => 0,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Monthly',
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 0,
        ]);

        InvoiceCalculator::recalculate($invoice->fresh());

        $invoice->refresh();
        $this->assertSame(1000.0, (float) $invoice->subtotal);
        $this->assertSame(45.0, (float) $invoice->tax_amount);
        $this->assertSame(945.0, (float) $invoice->total);
    }

    public function test_skips_void_invoices(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 5,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'C',
            'phone' => '01800000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 999,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 999,
            'amount_paid' => 0,
            'status' => 'void',
        ]);

        InvoiceCalculator::recalculate($invoice->fresh());

        $invoice->refresh();
        $this->assertSame(999.0, (float) $invoice->subtotal);
    }

    public function test_applies_sd_percent_on_net_after_discounts(): void
    {
        $package = Package::query()->create([
            'name' => 'SD Plan',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 10,
            'sd_percent' => 5,
            'withholding_percent' => 2,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'SD Customer',
            'phone' => '01700000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'sd_amount' => 0,
            'withholding_amount' => 0,
            'discount_amount' => 100,
            'coupon_discount_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'package',
            'description' => 'Monthly',
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 0,
        ]);

        InvoiceCalculator::recalculate($invoice->fresh());
        $invoice->refresh();

        $this->assertSame(1000.0, (float) $invoice->subtotal);
        $this->assertSame(90.0, (float) $invoice->tax_amount);
        $this->assertSame(45.0, (float) $invoice->sd_amount);
        $this->assertSame(18.0, (float) $invoice->withholding_amount);
        $this->assertSame(1035.0, (float) $invoice->total);
    }
}
