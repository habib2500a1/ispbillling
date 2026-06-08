<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Services\Notifications\CommsHubDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommsHubDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_kpi_structure(): void
    {
        NotificationLog::query()->create([
            'tenant_id' => 1,
            'channel' => 'sms',
            'event' => 'payment_success',
            'recipient' => '+8801700000000',
            'status' => 'sent',
            'message' => 'Test',
        ]);

        $snapshot = app(CommsHubDashboardService::class)->snapshot();

        $this->assertArrayHasKey('kpis', $snapshot);
        $this->assertArrayHasKey('sms_today', $snapshot['kpis']);
        $this->assertArrayHasKey('channels', $snapshot);
        $this->assertArrayHasKey('billing_automation', $snapshot);
    }

    public function test_search_requires_minimum_length(): void
    {
        $results = app(CommsHubDashboardService::class)->search('a');

        $this->assertSame([], $results);
    }
}
