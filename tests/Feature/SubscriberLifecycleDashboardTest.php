<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Dashboard\SubscriberLifecycleDashboardService;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberLifecycleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_monthly_new_lines_and_renewals_for_six_months(): void
    {
        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $newThisMonth = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'New Line',
            'phone' => '01710000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'N1001',
            'joined_at' => now()->startOfMonth()->addDay(),
        ]);

        $existing = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Old User',
            'phone' => '01710000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'O1001',
            'joined_at' => now()->subMonths(2),
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $existing->id,
            'amount' => 500,
            'status' => 'completed',
            'payment_type' => PaymentType::PAYMENT,
            'method' => 'cash',
            'paid_at' => now(),
            'recorded_by' => User::factory()->create(['tenant_id' => 1])->id,
        ]);

        $payload = app(SubscriberLifecycleDashboardService::class)->payload(1, 6);

        $this->assertSame(6, $payload['months']);
        $this->assertCount(6, $payload['new_lines']['labels']);
        $this->assertCount(6, $payload['renewals']['labels']);
        $this->assertSame(1, $payload['mtd_new_lines']);
        $this->assertSame(1, $payload['today_renewals']);
        $this->assertSame(1, $payload['mtd_renewals']);
        $this->assertSame(1, $payload['new_lines']['values'][5]);
        $this->assertGreaterThanOrEqual(1, $payload['renewals']['values'][5]);
        $this->assertSame($newThisMonth->id, Customer::query()->where('customer_code', 'N1001')->value('id'));
    }
}
