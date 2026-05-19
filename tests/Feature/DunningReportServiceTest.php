<?php

namespace Tests\Feature;

use App\Services\Billing\DunningReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DunningReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_returns_stages(): void
    {
        config(['billing.dunning.enabled' => true]);

        $snapshot = app(DunningReportService::class)->snapshot(1);

        $this->assertTrue($snapshot['enabled']);
        $this->assertIsArray($snapshot['stages']);
        $this->assertNotEmpty($snapshot['stages']);
    }
}
