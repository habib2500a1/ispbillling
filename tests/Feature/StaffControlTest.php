<?php

namespace Tests\Feature;

use App\Filament\Pages\StaffControlHub;
use App\Models\Branch;
use App\Models\User;
use App\Services\Staff\ActivityLogger;
use App\Services\Staff\IpAccessGuard;
use App\Services\Staff\TwoFactorService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_ip_guard_blocks_disallowed_ip(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 1,
            'allowed_ips' => ['10.0.0.1'],
        ]);

        $guard = app(IpAccessGuard::class);
        $this->assertTrue($guard->allows($user, '10.0.0.1'));
        $this->assertFalse($guard->allows($user, '192.168.1.50'));
    }

    public function test_activity_logger_creates_entry(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $this->actingAs($user);
        $log = app(ActivityLogger::class)->log('test.event', 'Test description');

        $this->assertDatabaseHas('activity_logs', [
            'id' => $log->id,
            'event' => 'test.event',
            'description' => 'Test description',
        ]);
    }

    public function test_two_factor_enable_and_verify(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        $code = (new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp($secret);

        $codes = $service->enable($user, $secret, $code);
        $this->assertIsArray($codes);
        $this->assertNotEmpty($codes);
        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertTrue($service->verify($user, $code));
    }

    public function test_isp_admin_can_open_staff_hub(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(StaffControlHub::class)
            ->assertSuccessful()
            ->assertSee('Admin & staff control');
    }

    public function test_branch_can_be_created(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $branch = Branch::query()->create([
            'tenant_id' => 1,
            'name' => 'Main office',
            'code' => 'HQ',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('branches', ['code' => 'HQ', 'name' => 'Main office']);
    }
}
