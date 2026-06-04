<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Models\VoiceTemplate;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderSmsVoiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function customer(int $tenantId): Customer
    {
        $package = Package::query()->create([
            'name' => 'Rem Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => $tenantId,
        ]);

        return Customer::createTrusted([
            'name' => 'Due User',
            'phone' => '01710000088',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => $tenantId,
        ]);
    }

    public function test_sms_on_sends_sms_only_not_voice(): void
    {
        config([
            'call_center.voice_call.enabled' => true,
            'notifications.log_delivery_only' => true,
            'notifications.events.invoice_due_soon.enabled' => true,
            'notifications.events.invoice_due_soon.channels' => [NotificationChannel::SMS],
        ]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'rem-sms'],
            ['name' => 'Rem SMS', 'is_active' => true],
        );

        $voice = VoiceTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Due voice',
            'transcript' => 'Pay now.',
            'is_active' => true,
        ]);

        SmsTemplate::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'invoice_due_soon'],
            [
                'name' => 'Due',
                'event_key' => 'invoice_due_soon',
                'body' => 'Bill due {name}',
                'is_enabled' => true,
                'voice_enabled' => true,
                'voice_template_id' => $voice->id,
            ],
        );

        $customer = $this->customer($tenant->id);

        app(NotificationDispatcher::class)->notifyCustomer($customer, 'invoice_due_soon', [
            'amount' => '100',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $customer->id,
            'event' => 'invoice_due_soon',
            'channel' => NotificationChannel::SMS,
        ]);
        $this->assertFalse(
            NotificationLog::query()
                ->where('customer_id', $customer->id)
                ->where('channel', 'voice')
                ->exists()
        );
    }

    public function test_sms_off_sends_voice_not_sms(): void
    {
        config([
            'call_center.voice_call.enabled' => true,
            'notifications.log_delivery_only' => true,
            'notifications.events.invoice_due_soon.enabled' => true,
            'notifications.events.invoice_due_soon.channels' => [NotificationChannel::SMS],
        ]);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'rem-voice'],
            ['name' => 'Rem Voice', 'is_active' => true],
        );

        $voice = VoiceTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Due voice',
            'transcript' => 'Pay by phone.',
            'is_active' => true,
        ]);

        SmsTemplate::withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'key' => 'invoice_due_soon'],
            [
                'name' => 'Due',
                'event_key' => 'invoice_due_soon',
                'body' => 'SMS body',
                'is_enabled' => false,
                'voice_enabled' => true,
                'voice_template_id' => $voice->id,
            ],
        );

        $customer = $this->customer($tenant->id);
        $this->assertSame($tenant->id, (int) $customer->tenant_id);

        app(NotificationDispatcher::class)->notifyCustomer($customer, 'invoice_due_soon');

        $this->assertFalse(
            NotificationLog::query()
                ->where('customer_id', $customer->id)
                ->where('event', 'invoice_due_soon')
                ->where('channel', NotificationChannel::SMS)
                ->exists()
        );
        $this->assertDatabaseHas('notification_logs', [
            'customer_id' => $customer->id,
            'channel' => 'voice',
        ]);
    }
}
