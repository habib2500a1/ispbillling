<?php

namespace Tests\Unit;

use App\Services\Billing\ProrationService;
use Carbon\Carbon;
use Tests\TestCase;

class ProrationServiceTest extends TestCase
{
    public function test_prorates_half_month(): void
    {
        $p0 = Carbon::parse('2026-05-01');
        $p1 = Carbon::parse('2026-05-31');
        $joined = Carbon::parse('2026-05-16');

        $amount = ProrationService::proratedAmount(1000.0, $p0, $p1, $joined);
        $this->assertGreaterThan(400, $amount);
        $this->assertLessThanOrEqual(548.0, $amount);
    }

    public function test_full_amount_when_active_entire_period(): void
    {
        $p0 = Carbon::parse('2026-05-01');
        $p1 = Carbon::parse('2026-05-31');
        $joined = Carbon::parse('2026-04-01');

        $this->assertSame(1000.0, ProrationService::proratedAmount(1000.0, $p0, $p1, $joined));
    }
}
