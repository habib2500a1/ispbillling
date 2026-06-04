<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerCommissionTier;
use App\Models\ResellerWalletTransaction;
use App\Services\Resellers\ResellerCommissionService;
use App\Services\Resellers\ResellerCustomerTransferService;
use App\Services\Resellers\ResellerHierarchyService;
use App\Services\Resellers\ResellerWalletLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResellerEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    public function test_hierarchy_path_syncs_on_create(): void
    {
        $parent = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Parent',
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
        ]);

        $child = Reseller::query()->create([
            'tenant_id' => 1,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'commission_type' => 'percent',
            'commission_value' => 5,
            'is_active' => true,
        ]);

        $child = $child->fresh();
        $this->assertStringContainsString((string) $parent->id, (string) $child->hierarchy_path);
        $this->assertStringContainsString((string) $child->id, (string) $child->hierarchy_path);
    }

    public function test_wallet_ledger_credits_main_wallet(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'R',
            'wallet_balance' => 0,
            'bonus_wallet_balance' => 0,
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
        ]);

        app(ResellerWalletLedgerService::class)->creditMain($reseller, 100, 'test_credit');

        $reseller->refresh();
        $this->assertEqualsWithDelta(100.0, (float) $reseller->wallet_balance, 0.01);
        $this->assertSame(1, ResellerWalletTransaction::query()->where('reseller_id', $reseller->id)->count());
    }

    public function test_tier_commission_calculation(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Tier R',
            'commission_type' => 'percent',
            'commission_value' => 5,
            'commission_mode' => 'tier',
            'is_active' => true,
        ]);

        ResellerCommissionTier::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'min_amount' => 0,
            'max_amount' => 500,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'sort_order' => 0,
        ]);

        ResellerCommissionTier::query()->create([
            'tenant_id' => 1,
            'reseller_id' => $reseller->id,
            'min_amount' => 500.01,
            'max_amount' => null,
            'commission_type' => 'percent',
            'commission_value' => 15,
            'sort_order' => 1,
        ]);

        $service = app(ResellerCommissionService::class);
        $this->assertEqualsWithDelta(40.0, $service->calculateCommission($reseller, 400), 0.01);
        $this->assertEqualsWithDelta(150.0, $service->calculateCommission($reseller, 1000), 0.01);
    }

    public function test_customer_transfer_completes_when_no_approval_required(): void
    {
        $from = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'From',
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $to = Reseller::query()->create([
            'tenant_id' => 1,
            'parent_id' => $from->id,
            'name' => 'To',
            'commission_type' => 'percent',
            'commission_value' => 0,
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
            'phone' => '01710009988',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'reseller_id' => $from->id,
            'tenant_id' => 1,
        ]);

        config(['reseller_enterprise.transfers.require_admin_approval' => false]);

        app(ResellerCustomerTransferService::class)->request(
            $customer,
            $from,
            $to,
            $from,
            'test transfer',
            false,
        );

        $customer->refresh();
        $this->assertSame((int) $to->id, (int) $customer->reseller_id);
    }

    public function test_descendant_detection(): void
    {
        $root = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Root',
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $child = Reseller::query()->create([
            'tenant_id' => 1,
            'parent_id' => $root->id,
            'name' => 'Child',
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
        ]);

        $hierarchy = app(ResellerHierarchyService::class);
        $this->assertTrue($hierarchy->isDescendantOf($child->fresh(), $root));
    }
}
