<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserTenantScopeRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_provider_retrieve_by_id_does_not_recurse_when_guard_has_no_user(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);

        Auth::logout();

        $retrieved = Auth::guard('web')->getProvider()->retrieveById($user->getAuthIdentifier());

        $this->assertNotNull($retrieved);
        $this->assertTrue($retrieved->is($user));
    }
}
