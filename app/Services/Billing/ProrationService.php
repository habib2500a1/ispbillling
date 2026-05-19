<?php

namespace App\Services\Billing;

use Carbon\CarbonInterface;

/**
 * Prorate a full-cycle amount when service is active only part of the billing window.
 */
final class ProrationService
{
    /**
     * @return float Rounded to 2 decimals, capped at full monthly amount.
     */
    public static function proratedAmount(
        float $fullCycleAmount,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        CarbonInterface $serviceStart,
        ?CarbonInterface $serviceEnd = null,
    ): float {
        $p0 = $periodStart->copy()->startOfDay();
        $p1 = $periodEnd->copy()->endOfDay();
        $s0 = $serviceStart->copy()->startOfDay();
        $s1 = ($serviceEnd ?? $periodEnd)->copy()->endOfDay();

        if ($s0->gt($p1) || $s1->lt($p0)) {
            return 0.0;
        }

        $effStart = $s0->max($p0);
        $effEnd = $s1->min($p1);

        $totalDays = max(1, $p0->diffInDays($p1) + 1);
        $activeDays = max(0, $effStart->diffInDays($effEnd) + 1);

        $ratio = min(1, $activeDays / $totalDays);

        return round(min($fullCycleAmount, $fullCycleAmount * $ratio), 2);
    }
}
