<?php

namespace Tests\Feature;

use App\Livewire\CustomerExcelUpload;
use App\Livewire\CustomerList;
use App\Livewire\InventoryHub;
use App\Livewire\MainSiteSetup;
use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServerErrorHotspotsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_site_settings_payment_tab_saves_without_rules_exception(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(MainSiteSetup::class)
            ->set('activeTab', 'payment')
            ->set('data.payment_bkash_enabled', 0)
            ->call('save', 'payment')
            ->assertHasNoErrors();
    }

    public function test_customer_datatable_search_does_not_use_missing_ppp_user_column(): void
    {
        $this->actingAs($this->admin());

        $ppp = PPPSecrets::create([
            'username' => 'habibfree',
            'password' => 'secret',
            'service' => 'pppoe',
            'status' => 'active',
        ]);
        CustomersInfo::create([
            'customer_unique_id' => 'FCNET100',
            'customer_name' => 'habibfree',
            'mobile' => '8801841558023',
            'status' => 'active',
            'ppp_user_id' => $ppp->id,
        ]);
        BillingInfo::create([
            'customer_bill_unique_id' => 'FCNET100',
            'monthly_rent' => 500,
            'due_amount' => 0,
        ]);

        $this->post(route('customers.data'), [
            'filter' => 'all',
            'search' => ['value' => 'habib'],
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ])->assertOk()->assertJsonPath('data.0.customer_unique_id', 'FCNET100');
    }

    public function test_inventory_hub_and_purchase_routes_exist(): void
    {
        $this->actingAs($this->admin());

        $this->assertSame(url('/inventory-purchases'), route('inventory-purchases'));
        $this->assertSame(url('/inventory-sales'), route('inventory-sales'));

        Livewire::test(InventoryHub::class)->assertOk()->assertSee('Purchases');
    }

    public function test_excel_demo_writes_to_a_writable_temp_file(): void
    {
        $this->actingAs($this->admin());

        $response = Livewire::test(CustomerExcelUpload::class)->call('downloadDemo');
        $response->assertHasNoErrors();
    }
}
