<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscribersListPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribers_list_page_renders_with_customer_rows(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');
        $this->actingAs($user);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'SUB'.random_int(1000, 9999),
            'name' => 'List Test Client',
            'phone' => '01710000001',
            'status' => 'active',
        ]);

        Livewire::test(ListCustomers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$customer]);
    }
}
