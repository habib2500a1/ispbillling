<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileLoginHubConfigTest extends TestCase
{
    public function test_mobile_config_includes_unified_login_hub(): void
    {
        config(['portal.enabled' => true, 'reseller_portal.enabled' => true]);

        $response = $this->getJson('/api/v1/mobile/config');

        $response->assertOk()
            ->assertJsonPath('login.hub_path', '/login')
            ->assertJsonPath('login.api_path', '/api/v1/mobile/login')
            ->assertJsonStructure([
                'login' => [
                    'hub_url',
                    'api_url',
                    'roles' => [
                        ['id', 'label', 'description', 'enabled', 'mode'],
                    ],
                ],
                'links' => ['login_hub', 'reseller_login'],
            ]);

        $roles = collect($response->json('login.roles'))->pluck('id')->all();
        $this->assertContains('customer', $roles);
        $this->assertContains('staff', $roles);
        $this->assertContains('reseller', $roles);

        $reseller = collect($response->json('login.roles'))->firstWhere('id', 'reseller');
        $this->assertSame('web', $reseller['mode'] ?? null);
        $this->assertStringContainsString('/reseller/login', (string) ($reseller['web_url'] ?? ''));
    }
}
