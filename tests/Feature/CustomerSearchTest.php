<?php

namespace Tests\Feature;

use App\Models\BillingInfo;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use App\Models\User;
use App\Services\Billing\CustomerSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function seedHabib(): CustomersInfo
    {
        $ppp = PPPSecrets::create([
            'username' => 'habibfree',
            'password' => 'secret',
            'service' => 'pppoe',
            'status' => 'active',
        ]);

        $customer = CustomersInfo::create([
            'customer_unique_id' => 'FCNET100',
            'customer_name' => 'habibfree',
            'contact_person' => 'Habibur Rahman',
            'mobile' => '8801841558023',
            'status' => 'active',
            'ppp_user_id' => $ppp->id,
        ]);

        BillingInfo::create([
            'customer_bill_unique_id' => 'FCNET100',
            'monthly_rent' => 500,
            'due_amount' => 0,
        ]);

        return $customer;
    }

    public function test_suggest_finds_name_id_username_and_local_mobile(): void
    {
        $this->actingAs($this->admin());
        $this->seedHabib();

        $search = app(CustomerSearch::class);

        $this->assertNotEmpty($search->suggest('habib'));
        $this->assertNotEmpty($search->suggest('FCNET100'));
        $this->assertNotEmpty($search->suggest('habibfree'));
        $this->assertNotEmpty($search->suggest('01841558023'));
        $this->assertSame('FCNET100', $search->suggest('habib')[0]['id']);
    }

    public function test_live_search_endpoint_returns_json(): void
    {
        $this->actingAs($this->admin());
        $this->seedHabib();

        $this->getJson(route('search.live', ['q' => 'habib']))
            ->assertOk()
            ->assertJsonPath('results.0.id', 'FCNET100')
            ->assertJsonPath('results.0.username', 'habibfree');
    }
}
