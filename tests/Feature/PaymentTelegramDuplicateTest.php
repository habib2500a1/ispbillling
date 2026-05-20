<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\StaffCollectionPaymentService;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTelegramDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function enableTelegramOps(): void
    {
        config([
            'notifications.telegram.enabled' => true,
            'notifications.telegram.bot_token' => 'test-token',
            'notifications.telegram.ops_chat_id' => '-100123',
            'notifications.events.payment_success.enabled' => true,
            'notifications.events.payment_success.channels' => ['email', 'sms', 'telegram'],
            'notifications.events.payment_success.telegram_ops' => true,
            'notifications.log_delivery_only' => true,
        ]);
    }

    public function test_wallet_apply_does_not_send_telegram_ops(): void
    {
        $this->enableTelegramOps();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $customer = $this->makeCustomer();
        $customer->forceFill(['account_balance' => 500])->saveQuietly();

        $before = now();

        $payment = Payment::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'amount' => 100,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::WALLET_APPLY,
        ]);

        $this->assertSame(PaymentType::WALLET_APPLY, $payment->fresh()->payment_type);

        $sent = NotificationLog::query()
            ->where('channel', NotificationChannel::TELEGRAM)
            ->where('event', NotificationEvent::PAYMENT_SUCCESS)
            ->where('status', 'sent')
            ->where('created_at', '>=', $before)
            ->count();

        $this->assertSame(0, $sent);
    }

    public function test_duplicate_collection_submit_sends_single_telegram(): void
    {
        $this->enableTelegramOps();
        Http::fake(['*' => Http::response(['ok' => true])]);

        $tenant = Tenant::query()->create(['name' => 'T', 'slug' => 'dup-isp', 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $customer = $this->makeCustomer($tenant);
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

        $before = now();

        $service = app(StaffCollectionPaymentService::class);
        $payload = [
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'method' => 'cash',
            'notes' => 'Full payment test',
        ];
        $service->record($user, $customer, $payload);
        $service->record($user, $customer, $payload);

        $sent = NotificationLog::query()
            ->where('channel', NotificationChannel::TELEGRAM)
            ->where('event', NotificationEvent::PAYMENT_SUCCESS)
            ->where('status', 'sent')
            ->where('created_at', '>=', $before)
            ->count();

        $this->assertSame(1, $sent);
        $this->assertSame(1, Payment::query()->where('customer_id', $customer->id)->where('created_at', '>=', $before)->count());
    }

    private function makeCustomer(?Tenant $tenant = null): Customer
    {
        $tenant ??= Tenant::query()->create(['name' => 'T', 'slug' => 'tg2-'.uniqid(), 'is_active' => true]);
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

        return Customer::createTrusted([
            'tenant_id' => $tenant->id,
            'customer_code' => 'RC'.random_int(1000, 9999),
            'name' => 'Test User',
            'phone' => '01800000000',
            'status' => 'active',
            'billing_day' => 5,
            'package_id' => $package->id,
        ]);
    }
}
