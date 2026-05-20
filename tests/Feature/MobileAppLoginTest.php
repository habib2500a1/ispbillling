<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileAppLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_login_route_exists_for_kotlin_app(): void
    {
        $this->postJson('/api/v1/login', [
            'login' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }
}
