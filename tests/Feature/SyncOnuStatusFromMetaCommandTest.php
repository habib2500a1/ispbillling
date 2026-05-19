<?php

namespace Tests\Feature;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncOnuStatusFromMetaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_onus_from_meta(): void
    {
        config(['olt_vendors.meta_sync_enabled' => true]);

        $serial = 'ONU-CMD-'.uniqid();
        Device::query()->create([
            'type' => 'onu',
            'serial_number' => $serial,
            'status' => 'assigned',
            'onu_oper_status' => 'unknown',
            'meta' => [
                'portal_onu_oper_status' => 'online',
                'portal_offline_reason' => '',
            ],
        ]);

        $this->artisan('isp:sync-onu-status-from-meta')
            ->assertSuccessful()
            ->expectsOutputToContain('Updated 1 ONU record');

        $this->assertSame('online', Device::query()->where('serial_number', $serial)->value('onu_oper_status'));
    }

    public function test_command_respects_disabled_config(): void
    {
        config(['olt_vendors.meta_sync_enabled' => false]);

        $this->artisan('isp:sync-onu-status-from-meta')
            ->assertSuccessful()
            ->expectsOutputToContain('disabled');
    }
}
