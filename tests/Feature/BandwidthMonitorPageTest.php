<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BandwidthMonitorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_bandwidth_monitor(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/bandwidth-monitor')
            ->assertOk()
            ->assertDontSee('This page has expired', false);
    }
}
