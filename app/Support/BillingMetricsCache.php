<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class BillingMetricsCache
{
    public static function flush(int $tenantId): void
    {
        for ($i = 0; $i < 3; $i++) {
            $hour = now()->subHours($i)->format('Y-m-d-H');
            Cache::forget("billing_dashboard:{$tenantId}:{$hour}");
        }

        for ($i = 0; $i < 10; $i++) {
            $minute = now()->subMinutes($i)->format('Y-m-d-H-i');
            Cache::forget("ops_dashboard:{$tenantId}:{$minute}");
        }
    }
}
