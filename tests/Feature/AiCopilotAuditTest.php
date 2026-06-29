<?php

namespace Tests\Feature;

use App\Models\AiInteractionLog;
use App\Services\Ai\AiOperationsOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCopilotAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_ask_logs_interaction(): void
    {
        $user = $this->makeAdminUser();
        $this->actingAs($user);

        $result = app(AiOperationsOrchestrator::class)->ask('operational summary');

        $this->assertNotEmpty($result['reply'] ?? '');
        $this->assertSame(1, AiInteractionLog::query()->withoutGlobalScopes()->where('tenant_id', 1)->count());
        $this->assertSame('staff', AiInteractionLog::query()->withoutGlobalScopes()->value('channel'));
    }
}
