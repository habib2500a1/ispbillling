<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Reseller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResellerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_login_page_loads(): void
    {
        $this->get('/reseller/login')->assertOk()->assertSee('Reseller portal');
    }

    public function test_reseller_can_login_and_view_dashboard(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Partner A',
            'code' => 'RSL-TEST-0001',
            'phone' => '01711112233',
            'email' => 'partner@example.com',
            'commission_type' => 'percent',
            'commission_value' => 5,
            'wallet_balance' => 100,
            'is_active' => true,
            'portal_password' => Hash::make('secret-pass'),
        ]);

        Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Sub One',
            'phone' => '01710009988',
            'status' => 'active',
            'billing_day' => 1,
            'reseller_id' => $reseller->id,
        ]);

        $this->post('/reseller/login', [
            'login' => 'RSL-TEST-0001',
            'password' => 'secret-pass',
        ])->assertRedirect(route('reseller.dashboard'));

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.dashboard'))
            ->assertOk()
            ->assertSee('Partner A');

        $this->actingAs($reseller, 'reseller')
            ->get(route('reseller.customers.index'))
            ->assertOk()
            ->assertSee('Sub One');
    }

    public function test_invalid_credentials_rejected(): void
    {
        Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Partner B',
            'commission_type' => 'percent',
            'commission_value' => 0,
            'is_active' => true,
            'portal_password' => Hash::make('right'),
        ]);

        $this->from('/reseller/login')
            ->post('/reseller/login', ['login' => 'Partner B', 'password' => 'wrong'])
            ->assertRedirect('/reseller/login')
            ->assertSessionHasErrors('login');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('reseller.dashboard'))->assertRedirect(route('reseller.login'));
    }
}
