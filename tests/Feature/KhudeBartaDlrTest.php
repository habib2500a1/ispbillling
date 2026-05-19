<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\SmsDeliveryReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhudeBartaDlrTest extends TestCase
{
    use RefreshDatabase;

    public function test_dlr_rejects_invalid_credentials(): void
    {
        config([
            'notifications.sms.api_key' => 'expected-key',
            'notifications.sms.secret_key' => 'expected-secret',
        ]);

        $this->get('/webhooks/sms/khudebarta/dlr?apikey=wrong&secretkey=wrong&messageid=1')
            ->assertStatus(401);
    }

    public function test_dlr_stores_report_and_updates_log(): void
    {
        config([
            'notifications.sms.api_key' => 'expected-key',
            'notifications.sms.secret_key' => 'expected-secret',
        ]);

        $log = NotificationLog::query()->create([
            'tenant_id' => 1,
            'channel' => 'sms',
            'recipient' => '8801712345678',
            'status' => 'sent',
            'event' => 'invoice_due',
            'meta' => [
                'gateway' => 'khudebarta',
                'gateway_message_id' => 'MSG-12345',
            ],
        ]);

        $this->get('/webhooks/sms/khudebarta/dlr?'.http_build_query([
            'apikey' => 'expected-key',
            'secretkey' => 'expected-secret',
            'messageid' => 'MSG-12345',
            'status' => 'Delivered',
        ]))->assertOk()->assertSee('OK');

        $this->assertDatabaseHas('sms_delivery_reports', [
            'gateway_message_id' => 'MSG-12345',
            'delivery_status' => 'delivered',
            'notification_log_id' => $log->id,
        ]);

        $log->refresh();
        $this->assertSame('delivered', $log->meta['dlr_status'] ?? null);
    }

    public function test_dlr_marks_failed_delivery(): void
    {
        config([
            'notifications.sms.api_key' => 'expected-key',
            'notifications.sms.secret_key' => 'expected-secret',
        ]);

        $log = NotificationLog::query()->create([
            'tenant_id' => 1,
            'channel' => 'sms',
            'recipient' => '8801712345678',
            'status' => 'sent',
            'event' => 'invoice_due',
            'meta' => ['gateway_message_id' => 'MSG-FAIL'],
        ]);

        $this->get('/webhooks/sms/khudebarta/dlr?'.http_build_query([
            'apikey' => 'expected-key',
            'secretkey' => 'expected-secret',
            'messageid' => 'MSG-FAIL',
            'status' => 'Failed',
            'StatusDescription' => 'Undelivered',
        ]))->assertOk();

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame('failed', SmsDeliveryReport::query()->where('gateway_message_id', 'MSG-FAIL')->value('delivery_status'));
    }
}
