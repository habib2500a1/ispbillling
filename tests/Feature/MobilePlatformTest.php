<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobilePlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_config_lists_all_phases_live(): void
    {
        $this->getJson('/api/v1/mobile/config')
            ->assertOk()
            ->assertJsonPath('phases.phase_1', 'live')
            ->assertJsonPath('features.offline_sync', true)
            ->assertJsonPath('features.ai_assistant', true);
    }

    public function test_realtime_config_requires_auth(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/mobile/realtime')
            ->assertOk()
            ->assertJsonStructure(['enabled', 'channel', 'events', 'polling_fallback_seconds']);
    }

    public function test_noc_dashboard_for_staff(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/staff/noc/dashboard')
            ->assertOk()
            ->assertJsonStructure(['olt_count', 'onu_count', 'customers_online', 'alerts']);
    }
}
