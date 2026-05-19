<?php

namespace Tests\Feature;

use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikServerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MikrotikServerProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_marks_offline_when_api_port_not_open(): void
    {
        $server = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'local-test',
            'host' => '127.0.0.1',
            'api_port' => 1,
            'use_ssl' => false,
            'legacy_login' => false,
            'api_username' => 'admin',
            'api_password' => 'admin',
            'is_enabled' => true,
            'last_api_status' => 'unknown',
        ]);

        app(MikrotikServerService::class)->probeAndPersist($server);

        $this->assertSame('offline', $server->fresh()->last_api_status);
        $this->assertNotNull($server->fresh()->last_checked_at);
    }

    public function test_poll_command_skips_when_disabled(): void
    {
        config(['mikrotik.poll_enabled' => false]);

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 's1',
            'host' => '127.0.0.1',
            'api_port' => 1,
            'api_username' => 'a',
            'api_password' => 'b',
            'is_enabled' => true,
        ]);

        Artisan::call('isp:mikrotik-poll-status');

        $this->assertStringContainsString('disabled', Artisan::output());
    }
}
