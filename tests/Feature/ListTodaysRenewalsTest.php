<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListTodaysCustomers;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\CustomerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ListTodaysRenewalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_todays_renewals_lists_customers_on_todays_billing_day(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $billingDay = min(28, max(1, (int) today()->day));

        $dueToday = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Due Today',
            'phone' => '01710001001',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => $billingDay,
            'package_id' => $package->id,
        ]);

        Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Other Day',
            'phone' => '01710001002',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => $billingDay === 1 ? 2 : 1,
            'package_id' => $package->id,
        ]);

        Livewire::actingAs($user)
            ->test(ListTodaysCustomers::class)
            ->assertCanSeeTableRecords([$dueToday])
            ->assertSee("Today's renewals");
    }
}
