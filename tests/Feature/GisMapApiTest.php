<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GisMapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_fetch_gis_map_payload(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        $token = $user->createToken('staff')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/staff/gis/map')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'payload' => ['nodes', 'edges', 'stats', 'ops' => ['intelligence' => ['faults', 'heatmaps', 'technicians']]],
            ]);
    }

    public function test_staff_gis_search_requires_query(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        $token = $user->createToken('staff')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/staff/gis/search?q=a')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['results']);
    }

    public function test_staff_gis_rca_for_unknown_customer(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        $token = $user->createToken('staff')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/staff/gis/customers/99999/rca')
            ->assertOk()
            ->assertJsonPath('found', false);
    }
}
