<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Support User',
            'phone' => '01739998877',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
    }

    public function test_live_chat_channel_label(): void
    {
        $ticket = SupportTicket::query()->create([
            'customer_id' => $this->customer()->id,
            'channel' => 'live_chat',
            'department' => 'technical_support',
            'priority' => 'medium',
            'subject' => 'Live chat',
            'description' => 'Hi',
            'status' => 'open',
        ]);

        $this->assertSame('Live chat', $ticket->channelLabel());
    }

    public function test_resolved_status_sets_resolved_at(): void
    {
        $ticket = SupportTicket::query()->create([
            'customer_id' => $this->customer()->id,
            'channel' => 'portal',
            'department' => 'billing',
            'priority' => 'low',
            'subject' => 'Bill',
            'description' => 'Question',
            'status' => 'open',
        ]);

        $this->assertNull($ticket->resolved_at);

        $ticket->update(['status' => 'resolved']);
        $ticket->refresh();

        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_sla_breach_detection(): void
    {
        $ticket = SupportTicket::query()->create([
            'customer_id' => $this->customer()->id,
            'channel' => 'portal',
            'department' => 'technical_support',
            'priority' => 'critical',
            'subject' => 'Down',
            'description' => 'No net',
            'status' => 'open',
            'sla_resolve_due_at' => now()->subHour(),
        ]);

        $this->assertTrue($ticket->isSlaBreached());
        $this->assertStringContainsString('Overdue', $ticket->slaRemainingLabel());
    }
}
