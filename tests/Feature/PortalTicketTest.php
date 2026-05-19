<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Notification::fake();
    }

    public function test_customer_can_create_and_view_ticket(): void
    {
        $customer = $this->makeCustomerWithPortal('secret');

        $this->actingAs($customer, 'customer')
            ->post(route('portal.tickets.store'), [
                'subject' => 'No line',
                'description' => 'Internet down since morning.',
                'department' => 'technical_support',
                'priority' => 'high',
                'issue_type' => 'outage',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->where('customer_id', $customer->id)->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);
        $this->assertSame('open', $ticket->status);
        $this->assertNotNull($ticket->sla_resolve_due_at);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->ticket_number, false)
            ->assertSee('No line', false);
    }

    public function test_customer_cannot_view_other_customers_ticket(): void
    {
        $a = $this->makeCustomerWithPortal('p1');
        $b = $this->makeCustomerWithPortal('p2');

        $ticket = SupportTicket::query()->create([
            'customer_id' => $b->id,
            'ticket_number' => 'TKT-TEST-0001',
            'channel' => 'portal',
            'department' => 'billing',
            'priority' => 'low',
            'status' => 'open',
            'subject' => 'Bill',
            'description' => 'Question',
        ]);

        $this->actingAs($a, 'customer')
            ->get(route('portal.tickets.show', $ticket))
            ->assertNotFound();
    }

    public function test_customer_can_reply_on_open_ticket(): void
    {
        $customer = $this->makeCustomerWithPortal('secret');
        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'ticket_number' => 'TKT-TEST-0002',
            'channel' => 'portal',
            'department' => 'billing',
            'priority' => 'medium',
            'status' => 'open',
            'subject' => 'Bill',
            'description' => 'Question',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.tickets.reply', $ticket), ['body' => 'More detail here.'])
            ->assertRedirect();

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'body' => 'More detail here.',
            'is_internal' => false,
        ]);

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
    }

    public function test_internal_message_hidden_from_customer_portal(): void
    {
        $customer = $this->makeCustomerWithPortal('secret');
        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'ticket_number' => 'TKT-TEST-0003',
            'channel' => 'portal',
            'department' => 'billing',
            'priority' => 'medium',
            'status' => 'open',
            'subject' => 'Bill',
            'description' => 'Question',
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'body' => 'Secret staff note',
            'is_internal' => true,
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'customer_id' => null,
            'user_id' => null,
            'body' => 'Public reply',
            'is_internal' => false,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Public reply', false)
            ->assertDontSee('Secret staff note', false);
    }

    private function makeCustomerWithPortal(string $plainPassword): Customer
    {
        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Portal User',
            'phone' => '017'.random_int(10000000, 99999999),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make($plainPassword),
        ]);
    }
}
