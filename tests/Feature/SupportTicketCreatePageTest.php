<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_support_ticket_create_page(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/support-tickets/create')
            ->assertOk();
    }

    public function test_isp_admin_can_search_subscribers_on_create_page(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        \App\Models\Package::query()->create([
            'tenant_id' => 1,
            'name' => 'Demo 10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = \App\Models\Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'TST0099',
            'name' => 'Searchable Ticket User',
            'phone' => '01710990099',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\SupportTicketResource\Pages\CreateSupportTicket::class)
            ->set('subscriberSearch', 'TST0099')
            ->call('runSubscriberSearch')
            ->assertSet('subscriberResults.0.customer_code', 'TST0099')
            ->call('selectSubscriber', (int) $customer->id)
            ->assertSet('data.customer_id', $customer->id);
    }

    public function test_create_page_prefills_customer_from_query_string(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        \App\Models\Package::query()->create([
            'tenant_id' => 1,
            'name' => 'Demo 10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = \App\Models\Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'TST0100',
            'name' => 'Prefill Ticket User',
            'phone' => '01710100100',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => 1,
        ]);

        $this->withServerVariables(['QUERY_STRING' => 'customer_id='.$customer->id])
            ->actingAs($user)
            ->get('/admin/support-tickets/create?customer_id='.$customer->id)
            ->assertOk();

        Livewire::actingAs($user)
            ->withQueryParams(['customer_id' => $customer->id])
            ->test(\App\Filament\Resources\SupportTicketResource\Pages\CreateSupportTicket::class)
            ->assertSet('data.customer_id', $customer->id)
            ->assertSet('selectedSubscriberId', $customer->id);
    }
}
