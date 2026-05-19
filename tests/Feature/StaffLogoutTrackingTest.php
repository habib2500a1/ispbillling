<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffLogoutTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_records_last_logout_timestamp(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->last_logout_at);
        $this->assertTrue($user->last_logout_at->isToday());
    }
}
