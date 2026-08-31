<?php

namespace App\Services\Billing;

use App\Models\MainSiteData;
use Carbon\Carbon;

final class MonthlyBillSchedule
{
    public static function mode(): string
    {
        return MainSiteData::getValue('monthly_bill_mode', 'customer') === 'global' ? 'global' : 'customer';
    }

    public static function day(): int
    {
        return max(1, min(28, (int) (MainSiteData::getValue('monthly_bill_day', 1) ?: 1)));
    }

    public static function shouldGenerateAllOn(Carbon $date): bool
    {
        $day = self::day();
        $effective = min($day, $date->daysInMonth);

        return (int) $date->day === $effective;
    }

    public static function eomInactiveAllowed(): bool
    {
        $raw = MainSiteData::getValue('eom_inactive_process', 1);

        return $raw === true || $raw === 1 || $raw === '1' || $raw === 'yes';
    }
}
