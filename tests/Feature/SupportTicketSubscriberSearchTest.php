<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketSubscriberSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_search_subscribers_via_json_endpoint(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        \App\Models\Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'TST0200',
            'name' => 'Habib Test User',
            'phone' => '01710000200',
            'status' => 'active',
            'billing_day' => 1,
        ]);

        $this->actingAs($user)
            ->getJson('/admin/support-tickets/subscriber-search?q=habib')
            ->assertOk()
            ->assertJsonPath('data.0.customer_code', 'TST0200');
    }
}
