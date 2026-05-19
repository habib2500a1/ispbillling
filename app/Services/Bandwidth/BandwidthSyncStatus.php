<?php

namespace App\Services\Bandwidth;

use Illuminate\Support\Facades\Cache;

final class BandwidthSyncStatus
{
    private const CACHE_KEY = 'bandwidth_sync_status_%d';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function store(int $tenantId, array $payload): void
    {
        Cache::put(sprintf(self::CACHE_KEY, $tenantId), array_merge($payload, [
            'updated_at' => now()->toIso8601String(),
        ]), now()->addHours(6));
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(int $tenantId): array
    {
        return Cache::get(sprintf(self::CACHE_KEY, $tenantId), [
            'api' => ['ok' => false, 'sessions' => 0, 'error' => 'Not synced yet'],
            'radius' => ['ok' => false, 'sessions' => 0, 'error' => 'Not synced yet'],
            'merged_active' => 0,
            'updated_at' => null,
        ]);
    }
}
