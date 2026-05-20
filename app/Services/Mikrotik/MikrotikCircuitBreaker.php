<?php

namespace App\Services\Mikrotik;

use Illuminate\Support\Facades\Cache;

/**
 * Skip routers that failed repeatedly (transient outage protection).
 */
final class MikrotikCircuitBreaker
{
    public function isOpen(int $serverId): bool
    {
        if (! config('mikrotik.circuit_breaker_enabled', true)) {
            return false;
        }

        return Cache::has($this->openKey($serverId));
    }

    public function recordSuccess(int $serverId): void
    {
        Cache::forget($this->failKey($serverId));
        Cache::forget($this->openKey($serverId));
    }

    public function recordFailure(int $serverId): void
    {
        if (! config('mikrotik.circuit_breaker_enabled', true)) {
            return;
        }

        $threshold = max(2, (int) config('mikrotik.circuit_failure_threshold', 3));
        $failKey = $this->failKey($serverId);
        $count = (int) Cache::get($failKey, 0) + 1;
        Cache::put($failKey, $count, now()->addMinutes(5));

        if ($count >= $threshold) {
            $cooldown = max(30, (int) config('mikrotik.circuit_open_seconds', 120));
            Cache::put($this->openKey($serverId), true, now()->addSeconds($cooldown));
        }
    }

    private function failKey(int $serverId): string
    {
        return 'mikrotik_cb_fail:'.$serverId;
    }

    private function openKey(int $serverId): string
    {
        return 'mikrotik_cb_open:'.$serverId;
    }
}
