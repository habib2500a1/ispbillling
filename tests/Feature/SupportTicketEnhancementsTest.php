<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\SupportTicket;
use App\Services\Support\SupportSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $meta = []): Customer
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
            'meta' => $meta,
        ]);
    }

    public function test_isp_ticket_number_format(): void
    {
        $ticket = SupportTicket::query()->create([
            'customer_id' => $this->customer()->id,
            'channel' => 'portal',
            'department' => 'technical_support',
            'priority' => 'medium',
            'subject' => 'Down',
            'description' => 'No net',
            'status' => 'open',
        ]);

        $this->assertMatchesRegularExpression('/^ISP-\d{4}-\d{6}$/', $ticket->ticket_number);
    }

    public function test_corporate_sla_profile_applies_faster_first_response(): void
    {
        $customer = $this->customer(['tag_corporate' => true]);
        $ticket = SupportTicket::query()->create([
            'customer_id' => $customer->id,
            'channel' => 'portal',
            'department' => 'technical_support',
            'priority' => 'high',
            'subject' => 'Corp line',
            'description' => 'Slow',
            'status' => 'open',
        ]);

        $this->assertSame('corporate', $ticket->sla_profile);
        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertTrue($ticket->first_response_due_at->lessThanOrEqualTo(now()->addMinutes(31)));
        $this->assertNotNull($ticket->eta_at);
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

    public function test_first_response_sla_helper(): void
    {
        $ticket = SupportTicket::query()->create([
            'customer_id' => $this->customer()->id,
            'channel' => 'portal',
            'department' => 'technical_support',
            'priority' => 'medium',
            'subject' => 'Help',
            'description' => 'Need help',
            'status' => 'open',
            'first_response_due_at' => now()->subMinutes(5),
        ]);

        $sla = app(SupportSlaService::class);
        $this->assertTrue($sla->isFirstResponseBreached($ticket));
        $this->assertStringContainsString('overdue', strtolower($sla->firstResponseRemainingLabel($ticket)));
    }

    public function test_assigned_status_enum_label(): void
    {
        $this->assertSame('Assigned', SupportTicket::STATUSES['assigned']);
        $this->assertSame('Pending customer', SupportTicket::STATUSES['pending_customer']);
    }
}
