<?php

namespace Tests\Feature;

use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentLink;
use App\Services\Billing\DunningLadderService;
use App\Services\Billing\FupUsageAlertService;
use App\Services\Billing\ScheduledPackageChangeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartBillingExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_downgrade_applies_on_date(): void
    {
        $current = Package::query()->create([
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 800,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $smaller = Package::query()->create([
            'name' => '5M',
            'type' => 'residential',
            'download_mbps' => 5,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Down',
            'phone' => '01726666666',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $current->id,
            'pending_package_id' => $smaller->id,
            'pending_package_effective_date' => now()->toDateString(),
        ]);

        $stats = app(ScheduledPackageChangeService::class)->applyDueChanges(null, false);
        $this->assertSame(1, $stats['applied']);
        $customer->refresh();
        $this->assertSame($smaller->id, $customer->package_id);
        $this->assertNull($customer->pending_package_id);
    }

    public function test_dunning_creates_payment_link(): void
    {
        config([
            'billing.dunning.enabled' => true,
            'billing.dunning.include_payment_link' => true,
            'notifications.events.invoice_due_today.enabled' => true,
        ]);

        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Due',
            'phone' => '01727777777',
            'email' => 'due@test.com',
            'status' => 'active',
            'package_id' => $package->id,
        ]);

        Invoice::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-TEST-1',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'subtotal' => 500,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $result = app(DunningLadderService::class)->run(false);
        $this->assertGreaterThan(0, $result['sent']);
        $this->assertTrue(PaymentLink::query()->where('customer_id', $customer->id)->exists());
    }

    public function test_fup_alert_detects_high_usage(): void
    {
        config(['billing.fup_alerts.enabled' => true, 'billing.fup_alerts.warn_percent' => 50]);

        $package = Package::query()->create([
            'name' => 'FUP',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'included_data_gb' => 1,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Heavy',
            'phone' => '01728888888',
            'email' => 'heavy@test.com',
            'status' => 'active',
            'billing_day' => (int) now()->day,
            'package_id' => $package->id,
        ]);

        BandwidthUsageDaily::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'usage_date' => now()->toDateString(),
            'bytes_in' => (int) (25 * 1073741824),
            'bytes_out' => 0,
        ]);

        $usage = app(FupUsageAlertService::class)->periodUsagePercent($customer, $package);
        $this->assertNotNull($usage);
        $this->assertGreaterThanOrEqual(50, $usage['percent']);
    }
}
