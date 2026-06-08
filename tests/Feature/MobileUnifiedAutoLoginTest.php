<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileUnifiedAutoLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_login_requires_credentials(): void
    {
        $this->postJson('/api/v1/mobile/login', [
            'login' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_explicit_role_still_works(): void
    {
        $this->postJson('/api/v1/mobile/login', [
            'role' => 'customer',
            'login' => '01700000000',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
}
