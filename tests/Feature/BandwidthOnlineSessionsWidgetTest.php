<?php

namespace Tests\Feature;

use App\Filament\Widgets\BandwidthOnlineSessionsWidget;
use App\Models\Customer;
use App\Models\PppSessionLog;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BandwidthOnlineSessionsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_sessions_widget_renders_with_api_sync_badge(): void
    {
        TenantResolver::fake(1);
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'ppp-online-1',
            'name' => 'Online User',
            'phone' => '01711111111',
            'status' => 'active',
        ]);

        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'mt1-session-1',
            'username' => 'ppp-online-1',
            'bytes_in' => 1000,
            'bytes_out' => 2000,
            'peak_rate_in_bps' => 5000,
            'peak_rate_out_bps' => 1000,
            'started_at' => now(),
            'status' => 'active',
            'meta' => ['sources' => ['api' => now()->toIso8601String()]],
        ]);

        Livewire::actingAs($user)
            ->test(BandwidthOnlineSessionsWidget::class)
            ->assertOk();
    }
}
