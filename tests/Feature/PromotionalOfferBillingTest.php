<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PromotionalOffer;
use App\Models\Tenant;
use App\Services\Billing\CouponApplicator;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Billing\PackageChangeQuoteService;
use App\Services\Billing\PromotionalOfferApplicator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionalOfferBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_applicator_applies_to_open_invoice(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'promo-direct-test'],
            ['name' => 'Direct ISP', 'is_active' => true],
        );

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'DirectPkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $promo = PromotionalOffer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Direct 10%',
            'discount_type' => PromotionalOffer::TYPE_PERCENT,
            'discount_value' => 10,
            'package_ids' => [$package->id],
            'is_active' => true,
        ]);

        $this->assertTrue($promo->isValidAt(now()));
        $this->assertTrue($promo->appliesToPackage($package->id));
        $this->assertSame(
            1,
            PromotionalOffer::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(),
        );

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'DIR001',
            'name' => 'Direct Client',
            'phone' => '01710000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $this->assertSame($tenant->id, (int) $customer->fresh()->tenant_id);

        $invoice = \App\Models\Invoice::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'sd_amount' => 0,
            'withholding_amount' => 0,
            'discount_amount' => 0,
            'coupon_discount_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        \App\Models\InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'package',
            'description' => 'Test',
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
            'sort_order' => 0,
        ]);

        \App\Services\Billing\InvoiceCalculator::recalculate($invoice->fresh());

        $resolvedPackage = Package::query()->withoutGlobalScopes()->find($package->id);
        $best = PromotionalOfferApplicator::bestForCustomer($customer, $resolvedPackage, null, 1000.0);
        $this->assertNotNull($best, 'Expected an active offer for tenant '.$tenant->id.' package '.$package->id);

        $this->assertTrue(PromotionalOfferApplicator::applyBestToInvoice($invoice->fresh()));
        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->promotional_offer_id);
        $this->assertEquals(100.0, (float) $invoice->promotional_offer_discount_amount);
    }

    public function test_monthly_invoice_auto_applies_promotional_offer(): void
    {
        config([
            'billing.setup_fee_on_first_invoice' => false,
            'network.mikrotik_push_enabled' => false,
        ]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'promo-bill-test'],
            ['name' => 'Promo ISP', 'is_active' => true],
        );

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Basic',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 1000,
            'billing_cycle_type' => 'daily',
            'billing_cycle_days' => 30,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        PromotionalOffer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '10% off Basic',
            'discount_type' => PromotionalOffer::TYPE_PERCENT,
            'discount_value' => 10,
            'package_ids' => [$package->id],
            'is_active' => true,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addMonth(),
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'PROMO001',
            'name' => 'Promo Client',
            'phone' => '01711112222',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
            'joined_at' => now()->subMonths(2),
        ]);

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true);
        $this->assertNotNull($invoice);

        $invoice = $invoice->fresh();
        $this->assertNotNull($invoice->promotional_offer_id);
        $this->assertGreaterThan(0, (float) $invoice->promotional_offer_discount_amount);
        $this->assertLessThan((float) $invoice->subtotal, (float) $invoice->total);
    }

    public function test_coupon_replaces_promotional_offer(): void
    {
        config(['network.mikrotik_push_enabled' => false]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'promo-coupon-test'],
            ['name' => 'Promo Coupon ISP', 'is_active' => true],
        );

        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Std',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_type' => 'daily',
            'billing_cycle_days' => 30,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        PromotionalOffer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '5% promo',
            'discount_type' => PromotionalOffer::TYPE_PERCENT,
            'discount_value' => 5,
            'is_active' => true,
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'PROMO002',
            'name' => 'Coupon Client',
            'phone' => '01711113333',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
            'joined_at' => now()->subMonths(2),
        ]);

        $invoice = InvoiceGenerator::generateForCustomer($customer, Carbon::now(), true);
        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->fresh()->promotional_offer_id);

        $coupon = \App\Models\Coupon::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'SAVE50',
            'discount_type' => \App\Models\Coupon::TYPE_FIXED_AMOUNT,
            'value' => 50,
            'is_active' => true,
        ]);

        CouponApplicator::apply($invoice->fresh(), 'SAVE50');

        $invoice = $invoice->fresh();
        $this->assertEquals($coupon->id, $invoice->coupon_id);
        $this->assertNull($invoice->promotional_offer_id);
        $this->assertEquals(0.0, (float) $invoice->promotional_offer_discount_amount);
        $subtotal = (float) $invoice->subtotal;
        $this->assertEquals(min(50.0, $subtotal), (float) $invoice->coupon_discount_amount);
        $this->assertEquals(max(0, round($subtotal - (float) $invoice->coupon_discount_amount, 2)), (float) $invoice->total);
    }

    public function test_package_upgrade_quote_includes_promotion(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'promo-upgrade-test'],
            ['name' => 'Upgrade ISP', 'is_active' => true],
        );

        $small = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Small',
            'type' => 'residential',
            'download_mbps' => 5,
            'price_monthly' => 500,
            'billing_cycle_type' => 'monthly',
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $big = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Big',
            'type' => 'residential',
            'download_mbps' => 50,
            'price_monthly' => 1000,
            'billing_cycle_type' => 'monthly',
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        PromotionalOffer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Upgrade 100 off',
            'discount_type' => PromotionalOffer::TYPE_FIXED,
            'discount_value' => 100,
            'package_ids' => [$big->id],
            'is_active' => true,
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'PROMO003',
            'name' => 'Upgrade Client',
            'phone' => '01711114444',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $small->id,
            'joined_at' => now()->subMonths(3),
        ]);

        $preview = PromotionalOfferApplicator::previewDiscount($customer, $big, 250.0);
        $this->assertEquals(100.0, $preview);

        $quote = app(PackageChangeQuoteService::class)->quote($customer, $big);

        $this->assertTrue($quote['is_upgrade']);
        $this->assertArrayHasKey('promotional_discount', $quote);
    }

    public function test_offer_skipped_for_wrong_package(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'promo-scope-test'],
            ['name' => 'Scope ISP', 'is_active' => true],
        );

        $packageA = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'A',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 800,
            'is_active' => true,
        ]);

        $packageB = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'B',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 1200,
            'is_active' => true,
        ]);

        PromotionalOffer::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'B only',
            'discount_type' => PromotionalOffer::TYPE_FIXED,
            'discount_value' => 200,
            'package_ids' => [$packageB->id],
            'is_active' => true,
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'PROMO004',
            'name' => 'Scope Client',
            'phone' => '01711115555',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $packageA->id,
        ]);

        $discount = PromotionalOfferApplicator::previewDiscount($customer, $packageA, 800);

        $this->assertEquals(0.0, $discount);
    }
}
