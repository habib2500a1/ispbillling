<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Models\ResellerSettlement;
use App\Models\User;
use App\Services\Resellers\ResellerSettlementService;
use App\Support\ResellerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResellerSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_can_submit_settlement_request(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Wallet Partner',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'wallet_balance' => 1000,
            'is_active' => true,
        ]);

        $settlement = app(ResellerSettlementService::class)->submitRequest(
            $reseller,
            500,
            'Weekly deposit',
            50,
        );

        $this->assertSame(ResellerSettlement::STATUS_PENDING, $settlement->status);
        $this->assertEquals(450.0, (float) $settlement->net_amount);
    }

    public function test_admin_can_approve_settlement_and_debit_wallet(): void
    {
        Role::findOrCreate('isp-admin');
        $admin = User::factory()->create(['tenant_id' => 1]);
        $admin->assignRole('isp-admin');

        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Settle Partner',
            'commission_type' => 'percent',
            'commission_value' => 5,
            'wallet_balance' => 800,
            'is_active' => true,
        ]);

        $settlement = app(ResellerSettlementService::class)->submitRequest($reseller, 300);

        app(ResellerSettlementService::class)->approve($settlement, $admin);

        $this->assertSame(ResellerSettlement::STATUS_APPROVED, $settlement->fresh()->status);
        $this->assertEquals(500.0, (float) $reseller->fresh()->wallet_balance);
    }
}
