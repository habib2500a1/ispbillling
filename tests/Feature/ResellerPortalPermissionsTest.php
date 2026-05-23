<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResellerPortalPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sub_reseller_defaults_limit_settlement_route(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Sub Partner',
            'franchise_type' => ResellerType::SUB_RESELLER,
            'commission_type' => 'percent',
            'commission_value' => 5,
            'is_active' => true,
            'portal_password' => Hash::make('secret'),
            'portal_permissions' => [
                ResellerPortalPermission::CUSTOMER_VIEW,
                ResellerPortalPermission::COMMISSION_VIEW,
            ],
        ]);

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.customers.index'))
            ->assertOk();

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.settlements.index'))
            ->assertForbidden();
    }

    public function test_settlement_permission_allows_access(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Full Partner',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'wallet_balance' => 500,
            'is_active' => true,
            'portal_password' => Hash::make('secret'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.settlements.index'))
            ->assertOk()
            ->assertSee('Settlement requests');
    }
}
