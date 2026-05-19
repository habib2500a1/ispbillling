<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Olt\OnuStatusFromMetaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnuStatusFromMetaSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_meta_keys_to_columns(): void
    {
        $onu = Device::query()->create([
            'type' => 'onu',
            'serial_number' => 'ONU-META-'.uniqid(),
            'status' => 'assigned',
            'onu_oper_status' => 'unknown',
            'offline_reason' => null,
            'meta' => [
                'portal_onu_oper_status' => 'offline',
                'portal_offline_reason' => 'Fiber cut upstream.',
            ],
        ]);

        $svc = new OnuStatusFromMetaSyncService;
        $this->assertTrue($svc->applyMetaToDevice($onu->fresh()));

        $fresh = $onu->fresh();
        $this->assertSame('offline', $fresh->onu_oper_status);
        $this->assertSame('Fiber cut upstream.', $fresh->offline_reason);
        $this->assertNotNull($fresh->last_polled_at);
    }

    public function test_noop_when_meta_missing_keys(): void
    {
        $onu = Device::query()->create([
            'type' => 'onu',
            'serial_number' => 'ONU-NO-'.uniqid(),
            'status' => 'assigned',
            'meta' => ['other' => 'x'],
        ]);

        $svc = new OnuStatusFromMetaSyncService;
        $this->assertFalse($svc->applyMetaToDevice($onu->fresh()));
    }
}
