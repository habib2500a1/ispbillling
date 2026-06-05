<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileConfigTest extends TestCase
{
    public function test_mobile_config_returns_links_and_features(): void
    {
        $response = $this->getJson('/api/v1/mobile/config');

        $response->assertOk()
            ->assertJsonStructure([
                'app_name',
                'login' => ['hub_url', 'api_url', 'roles'],
                'links' => ['base', 'pay', 'login_hub', 'portal_login', 'reseller_login', 'admin', 'apk'],
                'staff_paths' => ['billing', 'collect', 'tickets'],
                'features' => ['bkash', 'portal'],
                'ticket' => ['departments', 'priorities', 'defaults'],
                'packages',
            ]);
    }
}
