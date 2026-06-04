<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\Integrations\TenantApiSettingsService;
use App\Services\Tenant\TenantScopedConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hmac_signature_round_trip(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'api-hmac-test'],
            ['name' => 'API Test', 'is_active' => true],
        );

        $service = app(TenantApiSettingsService::class);
        $generated = $service->regenerateWebhookHmacSecret($tenant->id);
        $payload = '{"direction":"outbound","phone":"01710001111"}';
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $generated['plaintext']);

        $this->assertTrue($service->verifyWebhookSignature($tenant->id, $payload, $signature));
        $this->assertFalse($service->verifyWebhookSignature($tenant->id, $payload, 't='.$timestamp.',v1=deadbeef'));
    }

    public function test_api_host_override(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'api-host-test'],
            ['name' => 'Host Test', 'is_active' => true],
        );

        $service = app(TenantApiSettingsService::class);
        $service->saveApiHost($tenant->id, 'bill.example.com');

        TenantScopedConfig::apply($tenant->id);

        $this->assertSame('bill.example.com', $service->apiHost($tenant->id));
    }
}
