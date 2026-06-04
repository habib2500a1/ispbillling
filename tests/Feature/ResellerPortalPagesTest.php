<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResellerPortalPagesTest extends TestCase
{
    use RefreshDatabase;

    private function franchiseReseller(): Reseller
    {
        return Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Franchise Partner',
            'code' => 'RSL-PAGES-01',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'wallet_balance' => 1000,
            'is_active' => true,
            'portal_password' => Hash::make('secret-pass'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);
    }

    public function test_wallet_page_uses_english_pro_layout(): void
    {
        $this->actingAs($this->franchiseReseller(), 'reseller')
            ->get(route('reseller.wallet.index'))
            ->assertOk()
            ->assertSee('Wallet statement', false);
    }

    public function test_settlements_page_uses_english_pro_layout(): void
    {
        $this->actingAs($this->franchiseReseller(), 'reseller')
            ->get(route('reseller.settlements.index'))
            ->assertOk()
            ->assertSee('New request', false);
    }

    public function test_tickets_index_uses_english_pro_layout(): void
    {
        $this->actingAs($this->franchiseReseller(), 'reseller')
            ->get(route('reseller.tickets.index'))
            ->assertOk()
            ->assertSee('Support tickets', false);
    }

    public function test_hub_page_uses_english_pro_layout(): void
    {
        $reseller = $this->franchiseReseller();

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.hub'))
            ->assertOk()
            ->assertSee('Enterprise partner portal', false)
            ->assertSee($reseller->name, false);
    }

    public function test_sub_reseller_show_route_rejects_create_slug(): void
    {
        $parent = $this->franchiseReseller();

        $this->actingAs($parent, 'reseller')
            ->get('/reseller/sub-resellers/create')
            ->assertOk()
            ->assertDontSee('404', false);
    }
}
