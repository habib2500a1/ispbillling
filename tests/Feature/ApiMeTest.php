<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_me_returns_user_with_token(): void
    {
        $user = User::factory()->create(['name' => 'API User']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('name', 'API User')
            ->assertJsonPath('email', $user->email)
            ->assertJsonPath('tenant_id', $user->tenant_id);
    }
}
