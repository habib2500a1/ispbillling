<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FieldVisit;
use App\Models\Package;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_and_get_dashboard(): void
    {
        $customer = $this->makeCustomer('mobile-secret');

        $login = $this->postJson('/api/v1/customer/login', [
            'login' => $customer->phone,
            'password' => 'mobile-secret',
        ]);

        $login->assertOk()->assertJsonStructure(['token', 'customer' => ['id', 'name']]);
        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/customer/dashboard')
            ->assertOk()
            ->assertJsonStructure(['customer', 'total_due', 'recent_bills']);
    }

    public function test_customer_live_usage_endpoint(): void
    {
        $customer = $this->makeCustomer('usage-pass');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/customer/usage/live')
            ->assertOk()
            ->assertJsonStructure(['usage' => ['online', 'download_human', 'upload_human']]);
    }

    public function test_customer_can_register_device_token(): void
    {
        $customer = $this->makeCustomer('dev-pass');
        $token = $customer->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/customer/devices', [
                'token' => 'fcm-test-token-123',
                'platform' => 'android',
            ])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'tokenable_type' => Customer::class,
            'tokenable_id' => $customer->id,
            'app' => 'customer',
        ]);
    }

    public function test_technician_can_login_and_list_field_visits(): void
    {
        Role::findOrCreate('isp-engineer');
        $user = User::factory()->create(['tenant_id' => 1, 'email' => 'tech@test.com']);
        $user->assignRole('isp-engineer');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'tech@test.com',
            'password' => 'password',
        ]);
        $login->assertOk()->assertJsonStructure(['token', 'user']);

        $ticket = SupportTicket::query()->create([
            'tenant_id' => 1,
            'customer_id' => $this->makeCustomer('x')->id,
            'channel' => 'portal',
            'department' => 'technical_support',
            'priority' => 'medium',
            'subject' => 'Test',
            'description' => 'Test',
            'status' => 'open',
        ]);

        FieldVisit::query()->create([
            'tenant_id' => 1,
            'support_ticket_id' => $ticket->id,
            'assigned_to' => $user->id,
            'status' => 'scheduled',
            'scheduled_at' => now(),
        ]);

        $this->withToken($login->json('token'))
            ->getJson('/api/v1/technician/field-visits')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_staff_token_cannot_access_customer_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('staff')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/customer/dashboard')
            ->assertForbidden();
    }

    private function makeCustomer(string $password): Customer
    {
        $package = Package::query()->create([
            'name' => 'Mobile Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        return Customer::query()->create([
            'name' => 'Mobile User',
            'phone' => '017'.fake()->unique()->numerify('########'),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
            'portal_password' => Hash::make($password),
        ]);
    }
}
