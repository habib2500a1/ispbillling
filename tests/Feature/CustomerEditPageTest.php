<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerEditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_render_customer_edit_page(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Edit Me',
            'phone' => '01800000999',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_customer_edit_survives_invalid_mikrotik_ciphertext(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $package = Package::query()->create([
            'name' => 'Plan2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Bad Cipher',
            'phone' => '01800000888',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        $customer->getConnection()->table('customers')->where('id', $customer->id)->update([
            'mikrotik_ppp_password' => 'totally-not-laravel-encrypted',
        ]);

        Livewire::actingAs($user)
            ->test(EditCustomer::class, ['record' => $customer->fresh()->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_save_without_portal_change_preserves_portal_hash(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $package = Package::query()->create([
            'name' => 'Plan3',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Portal Keep',
            'phone' => '01800000777',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);
        $customer->update(['portal_password' => Hash::make('secret123')]);
        $hashBefore = $customer->fresh()->portal_password;

        Livewire::actingAs($user)
            ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->set('data.name', 'Portal Keep Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($hashBefore, $customer->fresh()->portal_password);
    }

    public function test_super_admin_without_tenant_can_open_customer_edit(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create(['tenant_id' => null]);
        $user->assignRole('super-admin');

        $package = Package::query()->create([
            'name' => 'Plan4',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Super Edit',
            'phone' => '01800000666',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertSuccessful();
    }
}
