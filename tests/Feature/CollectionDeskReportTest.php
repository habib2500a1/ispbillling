<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing\CollectionDeskReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionDeskReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_includes_customer_and_collector_details(): void
    {
        $collector = User::factory()->create(['name' => 'Karim Collector', 'tenant_id' => 1]);
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $customer = Customer::query()->create([
            'name' => 'Rahim Uddin',
            'phone' => '01711112233',
            'customer_code' => 'SUB-1001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 250,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $collector->id,
            'reference' => 'CASH-001',
        ]);

        $report = app(CollectionDeskReportService::class)->report(now(), now(), $collector->id);

        $this->assertSame(1, $report['count']);
        $this->assertSame('Karim Collector', $report['rows'][0]['collector_name']);
        $this->assertSame('Rahim Uddin', $report['rows'][0]['customer_name']);
        $this->assertSame('SUB-1001', $report['rows'][0]['customer_code']);
        $this->assertSame(250.0, $report['rows'][0]['amount']);
    }

    public function test_search_filters_by_customer_name(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);
        $c1 = Customer::query()->create([
            'name' => 'Alpha User',
            'phone' => '01710000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
        $c2 = Customer::query()->create([
            'name' => 'Beta User',
            'phone' => '01710000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);

        foreach ([$c1, $c2] as $c) {
            Payment::query()->create([
                'tenant_id' => 1,
                'customer_id' => $c->id,
                'amount' => 100,
                'method' => 'cash',
                'status' => 'completed',
                'paid_at' => now(),
            ]);
        }

        $report = app(CollectionDeskReportService::class)->report(now(), now(), null, 'alpha');

        $this->assertSame(1, $report['count']);
        $this->assertSame('Alpha User', $report['rows'][0]['customer_name']);
    }
}
