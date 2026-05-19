<?php

namespace Tests\Feature;

use App\Services\Dashboard\AiAnalyticsService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_insights_returns_recommendations(): void
    {
        $insights = app(AiAnalyticsService::class)->insights(1);

        $this->assertArrayHasKey('recommendations', $insights);
        $this->assertNotEmpty($insights['recommendations']);
    }
}
