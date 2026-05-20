<?php

namespace Tests\Unit;

use App\Support\BillingDefaults;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BillingDefaultsTest extends TestCase
{
    public function test_date_from_expire_day_uses_current_month_when_day_ahead(): void
    {
        Carbon::setTestNow('2026-06-10');
        $this->assertSame('2026-06-15', BillingDefaults::dateFromExpireDay(15));
        Carbon::setTestNow();
    }

    public function test_date_from_expire_day_rolls_to_next_month_when_day_passed(): void
    {
        Carbon::setTestNow('2026-06-20');
        $this->assertSame('2026-07-15', BillingDefaults::dateFromExpireDay(15));
        Carbon::setTestNow();
    }

    public function test_expire_day_from_date_returns_calendar_day(): void
    {
        $this->assertSame(28, BillingDefaults::expireDayFromDate('2026-05-28'));
        $this->assertSame('28', BillingDefaults::expireDayLabel('2026-05-28'));
    }
}
