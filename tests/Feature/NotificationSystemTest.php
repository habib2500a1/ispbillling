<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'Notify Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Notify User',
            'phone' => '01710000099',
            'email' => 'notify@example.com',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
        ]);
    }

    public function test_dispatcher_logs_when_delivery_only(): void
    {
        config([
            'notifications.log_delivery_only' => true,
            'notifications.email.enabled' => true,
            'notifications.events.payment_success.enabled' => true,
            'notifications.events.payment_success.channels' => ['email'],
        ]);

        $customer = $this->customer();

        app(NotificationDispatcher::class)->notifyCustomer($customer, NotificationEvent::PAYMENT_SUCCESS, [
            'amount' => '500.00',
            'invoice_number' => 'INV-1',
            'receipt_number' => 'RCP-1',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $customer->id,
            'event' => NotificationEvent::PAYMENT_SUCCESS,
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    public function test_payment_completion_creates_notification_log(): void
    {
        config([
            'notifications.log_delivery_only' => true,
            'notifications.events.payment_success.enabled' => true,
            'notifications.events.payment_success.channels' => ['email'],
            'notifications.events.payment_success.telegram_ops' => false,
        ]);

        $customer = $this->customer();

        Payment::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'amount' => 100,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => 'payment',
        ]);

        $this->assertTrue(
            NotificationLog::query()->where('event', NotificationEvent::PAYMENT_SUCCESS)->exists()
        );
    }

    public function test_template_renderer_substitutes_variables(): void
    {
        $rendered = \App\Services\Notifications\MessageTemplateRenderer::render('payment_success', [
            'name' => 'Ali',
            'amount' => '100',
            'invoice_number' => 'INV-9',
            'receipt_number' => 'RCP-9',
        ]);

        $this->assertStringContainsString('Ali', $rendered);
        $this->assertStringContainsString('INV-9', $rendered);
    }
}
