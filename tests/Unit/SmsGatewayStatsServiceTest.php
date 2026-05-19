<?php

namespace Tests\Unit;

use App\Models\NotificationLog;
use App\Services\Notifications\SmsGatewayStatsService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsGatewayStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_snapshot_counts_sms_logs(): void
    {
        NotificationLog::query()->create([
            'tenant_id' => 1,
            'event' => 'test',
            'channel' => 'sms',
            'recipient' => '01700000000',
            'status' => 'sent',
            'message' => 'Hi',
            'sent_at' => now(),
        ]);

        NotificationLog::query()->create([
            'tenant_id' => 1,
            'event' => 'test',
            'channel' => 'sms',
            'recipient' => '01700000001',
            'status' => 'failed',
            'message' => 'Fail',
            'error' => 'rejected',
        ]);

        $stats = app(SmsGatewayStatsService::class)->snapshot(false);

        $this->assertGreaterThanOrEqual(1, $stats['today_sent']);
        $this->assertGreaterThanOrEqual(1, $stats['month_failed']);
        $this->assertArrayHasKey('provider_label', $stats);
    }
}
