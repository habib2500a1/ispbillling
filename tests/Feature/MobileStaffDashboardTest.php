<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileStaffDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_returns_billing_and_stats(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        Sanctum::actingAs($user, ['staff']);

        $response = $this->getJson('/api/v1/staff/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'user_type', 'status'],
                'billing' => ['monthly_bill', 'collected_bill', 'due', 'discount'],
                'tickets' => ['total', 'pending', 'process'],
                'tasks' => ['total', 'pending', 'process'],
                'zone_collection_chart',
                'quick_actions',
            ]);
    }
}
