<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Network\AveisGponOnuSyncService;
use App\Services\Network\BdcomEponOnuSyncService;
use App\Services\Network\VsolGponOnuSyncService;
use App\Support\GponSnmpProfile;
use Tests\TestCase;

class VendorGponDriverTest extends TestCase
{
    public function test_bdcom_gpon_profile_inherits_epon_oids(): void
    {
        $oids = GponSnmpProfile::merged('bdcom_gpon');

        $this->assertArrayHasKey('bdcom_epon_onu_rx', $oids);
        $this->assertArrayHasKey('bdcom_epon_onu_tx', $oids);
    }

    public function test_bdcom_gpon_driver_supported_by_sync_service(): void
    {
        $olt = new Device(['type' => 'olt', 'olt_driver' => 'bdcom_gpon']);

        $this->assertTrue(app(BdcomEponOnuSyncService::class)->supportsDriver($olt));
    }

    public function test_config_driven_drivers_supported(): void
    {
        $sync = app(VsolGponOnuSyncService::class);

        foreach (['vsol_gpon', 'ecom_gpon', 'cdata_gpon', 'zte_gpon', 'fiberhome_gpon', 'nokia_gpon', 'raisecom_gpon'] as $driver) {
            $olt = new Device(['type' => 'olt', 'olt_driver' => $driver]);
            $this->assertTrue($sync->supportsDriver($olt), "Failed for {$driver}");
        }
    }

    public function test_generic_snmp_requires_oids(): void
    {
        $olt = new Device(['type' => 'olt', 'olt_driver' => 'generic_snmp', 'meta' => []]);
        $this->assertFalse(app(VsolGponOnuSyncService::class)->supportsDriver($olt));

        $olt->meta = ['snmp_onu_oids' => ['rx' => '1.3.6.1.4.1.1']];
        $this->assertTrue(app(VsolGponOnuSyncService::class)->supportsDriver($olt));
    }

    public function test_guess_driver_covers_vendors(): void
    {
        $this->assertSame('zte_gpon', \App\Services\Network\OltOnuSyncCoordinator::guessDriverFromSysDescr('ZTE C320 GPON OLT'));
        $this->assertSame('nokia_gpon', \App\Services\Network\OltOnuSyncCoordinator::guessDriverFromSysDescr('Nokia ISAM FX'));
        $this->assertSame('raisecom_gpon', \App\Services\Network\OltOnuSyncCoordinator::guessDriverFromSysDescr('Raisecom GPON OLT'));
    }

    public function test_aveis_drivers_supported(): void
    {
        $sync = app(AveisGponOnuSyncService::class);

        foreach (['aveis_gpon', 'aveis_epon'] as $driver) {
            $olt = new Device(['type' => 'olt', 'olt_driver' => $driver]);
            $this->assertTrue($sync->supportsDriver($olt), "Failed for {$driver}");
        }
    }

    public function test_aveis_epon_resolves_dedicated_profile_with_identity_columns(): void
    {
        $olt = new Device(['type' => 'olt', 'olt_driver' => 'aveis_epon']);

        $this->assertSame('aveis_epon', GponSnmpProfile::profileKeyForOlt($olt));

        $oids = GponSnmpProfile::merged('aveis_epon');

        // Inherits the enterprise ONU table from aveis_gpon and exposes tunable identity columns.
        $this->assertSame('1.3.6.1.4.1.50224.3.3.2.1', $oids['aveis_onu_table']);
        $this->assertSame(7, (int) $oids['aveis_onu_mac_column']);
        $this->assertSame(3, (int) $oids['aveis_onu_status_column']);
        $this->assertSame(2, (int) $oids['aveis_onu_label_column']);
    }

    public function test_aveis_epon_index_and_rx_decode(): void
    {
        // 0x01000102 = PON1, ONU2 on card 1.
        $parsed = AveisGponOnuSyncService::parseAveisIndex(16777474);
        $this->assertSame(['card_no' => 1, 'pon_no' => 1, 'onu_index' => 2], $parsed);

        // col15 divisor decode: 841 → ≈ -14.68 dBm.
        $this->assertEqualsWithDelta(-14.68, AveisGponOnuSyncService::decodeAveisRx(841), 0.05);
    }

    public function test_guess_driver_distinguishes_aveis_epon(): void
    {
        $this->assertSame(
            'aveis_epon',
            \App\Services\Network\OltOnuSyncCoordinator::guessDriverFromSysDescr('Aveis AV-OLT EPON 4-port'),
        );

        $this->assertSame(
            'aveis_gpon',
            \App\Services\Network\OltOnuSyncCoordinator::guessDriverFromSysDescr('Aveis AV-OLT-XE08-L3 GPON'),
        );
    }
}
