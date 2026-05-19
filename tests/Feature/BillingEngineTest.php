<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Services\Billing\CouponApplicator;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\LateFeeCalculator;
use App\Services\Billing\PackagePriceResolver;
use App\Support\BillingCycleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_cycle_scales_price_from_monthly(): void
    {
        $package = Package::query()->create([
            'name' => 'Daily',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 3000,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => BillingCycleType::DAILY,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $this->assertSame(100.0, PackagePriceResolver::resolveCyclePrice($package, null));
    }

    public function test_advance_billing_sets_shorter_due_date(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Advance',
            'phone' => '01720000001',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
            'billing_mode' => 'advance',
            'grace_period_days' => 10,
        ]);

        [$issue, $due] = InvoiceGenerator::resolveIssueAndDueDates($customer, now());
        $this->assertSame(now()->toDateString(), $issue);
        $this->assertSame(now()->addDays(3)->toDateString(), $due);
    }

    public function test_generate_bills_creates_daily_invoice(): void
    {
        $package = Package::query()->create([
            'name' => 'Daily plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 600,
            'billing_cycle_days' => 30,
            'billing_cycle_type' => BillingCycleType::DAILY,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'Daily user',
            'phone' => '01720000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        Artisan::call('isp:generate-bills', [
            '--force' => true,
            '--cycle' => BillingCycleType::DAILY,
        ]);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_late_fee_applies_after_grace(): void
    {
        $package = Package::query()->create([
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Late',
            'phone' => '01720000003',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'grace_period_days' => 5,
            'late_fee_fixed' => 50,
            'late_fee_percent' => 10,
            'late_fee_period' => 'daily',
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(15)->toDateString(),
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 1000,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $fee = LateFeeCalculator::calculateFee($invoice);
        $this->assertGreaterThan(0, $fee);
        $this->assertTrue(LateFeeCalculator::applyToInvoice($invoice));
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_type' => 'late_fee',
        ]);
    }

    public function test_coupon_percent_reduces_invoice_total(): void
    {
        $package = Package::query()->create([
            'name' => 'P3',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Coupon',
            'phone' => '01720000004',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $coupon = Coupon::query()->create([
            'code' => 'SAVE10',
            'discount_type' => Coupon::TYPE_PERCENT,
            'value' => 10,
            'is_active' => true,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 1000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 1000,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $invoice->items()->create([
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        CouponApplicator::apply($invoice->fresh(), 'SAVE10');
        $invoice->refresh();

        $this->assertSame($coupon->id, $invoice->coupon_id);
        $this->assertSame(100.0, (float) $invoice->coupon_discount_amount);
        $this->assertSame(900.0, (float) $invoice->total);
    }
}
