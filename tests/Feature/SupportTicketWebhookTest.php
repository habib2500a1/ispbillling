<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupportTicketWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Notification::fake();
    }

    public function test_webhook_rejects_missing_or_bad_secret(): void
    {
        config(['support.webhook_secret' => 'ok']);
        $this->postJson('/api/webhooks/support-ticket-ingest', [])->assertStatus(403);
        $this->postJson('/api/webhooks/support-ticket-ingest', [], ['X-ISP-Webhook-Secret' => 'wrong'])->assertStatus(403);
    }

    public function test_webhook_creates_ticket_with_valid_secret(): void
    {
        config(['support.webhook_secret' => 'secret-123']);

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

        $customer = Customer::query()->create([
            'name' => 'Hook User',
            'phone' => '01800000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make('x'),
        ]);

        $this->postJson(
            '/api/webhooks/support-ticket-ingest',
            [
                'customer_code' => $customer->customer_code,
                'subject' => 'WhatsApp ping',
                'description' => 'Customer text',
                'channel' => 'whatsapp',
                'department' => 'technical_support',
                'priority' => 'high',
            ],
            ['X-ISP-Webhook-Secret' => 'secret-123']
        )->assertCreated()
            ->assertJsonStructure(['ticket_number', 'id']);

        $this->assertDatabaseHas('support_tickets', [
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
        ]);
    }
}
