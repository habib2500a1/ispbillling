<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Reseller;
use App\Services\Resellers\ResellerBalanceService;
use App\Services\Resellers\ResellerCommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_accrues_on_payment(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'R1',
            'commission_type' => 'percent',
            'commission_value' => 10,
            'wallet_balance' => 0,
            'is_active' => true,
        ]);

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
            'name' => 'C',
            'phone' => '01710001122',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'reseller_id' => $reseller->id,
            'tenant_id' => 1,
        ]);

        $payment = Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 1000,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'payment',
        ]);

        $commission = app(ResellerCommissionService::class)->accrueFromPayment($payment);

        $this->assertNotNull($commission);
        $this->assertEqualsWithDelta(100.0, (float) $commission->commission_amount, 0.01);
    }

    public function test_balance_transfer_updates_wallets(): void
    {
        $parent = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Parent',
            'wallet_balance' => 500,
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $child = Reseller::query()->create([
            'tenant_id' => 1,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'wallet_balance' => 0,
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
        ]);

        app(ResellerBalanceService::class)->transfer($parent, $child, 200, 'Test transfer');

        $this->assertEqualsWithDelta(300.0, (float) $parent->fresh()->wallet_balance, 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $child->fresh()->wallet_balance, 0.01);
    }
}
