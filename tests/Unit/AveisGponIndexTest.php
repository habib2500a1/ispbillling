<?php

namespace Tests\Unit;

use App\Services\Network\AveisGponOnuSyncService;
use App\Services\Network\OltOnuSyncCoordinator;
use PHPUnit\Framework\TestCase;

class AveisGponIndexTest extends TestCase
{
    public function test_parses_pon_and_onu_from_snmp_index(): void
    {
        $parsed = AveisGponOnuSyncService::parseAveisIndex(16777473);
        $this->assertNotNull($parsed);
        $this->assertSame(1, $parsed['pon_no']);
        $this->assertSame(1, $parsed['onu_index']);
    }

    public function test_guesses_aveis_driver_from_sys_descr(): void
    {
        $this->assertSame('aveis_gpon', OltOnuSyncCoordinator::guessDriverFromSysDescr('AV-OLT-XE08-L3'));
    }
}
