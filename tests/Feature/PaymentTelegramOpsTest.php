<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTelegramOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_sends_single_telegram_ops_message_with_customer_code(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);

        config([
            'notifications.telegram.enabled' => true,
            'notifications.telegram.bot_token' => 'test-token',
            'notifications.telegram.ops_chat_id' => '-100123',
            'notifications.events.payment_success.enabled' => true,
            'notifications.events.payment_success.channels' => ['email'],
            'notifications.events.payment_success.telegram_ops' => true,
            'notifications.log_delivery_only' => true,
        ]);

        $tenant = Tenant::query()->create(['name' => 'T', 'slug' => 'tg-isp', 'is_active' => true]);
        $package = Package::query()->create([
            'tenant_id' => $tenant->id,
            'name' => '10M',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'RC9001',
            'name' => 'Habibur Rahman',
            'phone' => '01841558023',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
        ]);

        $invoice = Invoice::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 500,
            'tax_amount' => 0,
            'total' => 500,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ISP Administrator']);

        $before = now();

        $payment = Payment::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'recorded_by' => $user->id,
            'payment_type' => 'payment',
        ]);

        $opsLogs = NotificationLog::query()
            ->where('channel', 'telegram')
            ->where('event', NotificationEvent::PAYMENT_SUCCESS)
            ->where('status', 'sent')
            ->where('created_at', '>=', $before)
            ->get();

        $this->assertCount(1, $opsLogs, 'Expected exactly one Telegram ops message per payment');
        $body = (string) $opsLogs->first()->message;
        $this->assertStringContainsString('500.00', $body);
        $this->assertStringContainsString('RC9001', $body);
        $this->assertStringContainsString('Cash', $body);
        $this->assertStringNotContainsString('{PaidAmount}', $body);
        $this->assertStringNotContainsString('{{ClientID}}', $body);
    }
}
