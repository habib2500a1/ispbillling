<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Billing\CustomerLineGraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLineGraceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_line_grace_blocks_service_expired(): void
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
            'name' => 'Grace Test',
            'phone' => '01700009999',
            'status' => 'active',
            'billing_day' => 1,
            'grace_period_days' => 0,
            'package_id' => $package->id,
            'service_expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($customer->fresh()->isServiceExpired());

        CustomerLineGraceService::extendForCurrentMonth($customer->fresh(), 5);

        $this->assertFalse($customer->fresh()->isServiceExpired());
        $this->assertTrue(CustomerLineGraceService::hasActiveLineGrace($customer->fresh()));
    }
}
