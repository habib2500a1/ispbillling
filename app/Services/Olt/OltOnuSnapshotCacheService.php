<?php

namespace App\Services\Olt;

use App\Models\Device;
use Illuminate\Support\Facades\Cache;

/**
 * Redis snapshot cache for large OLT ONU tables (500k scale).
 */
final class OltOnuSnapshotCacheService
{
    public function remember(int $oltId, callable $builder, int $ttlSeconds = 60): array
    {
        if (! config('cache.default') || config('cache.default') === 'array') {
            return $builder();
        }

        return Cache::remember(
            $this->key($oltId),
            $ttlSeconds,
            fn (): array => $builder(),
        );
    }

    public function forget(int $oltId): void
    {
        Cache::forget($this->key($oltId));
    }

    /**
     * @return array{online: int, offline: int, total: int, unauthorized: int}
     */
    public function counts(Device $olt): array
    {
        return $this->remember($olt->id, function () use ($olt): array {
            $query = Device::query()->where('olt_id', $olt->id)->where('type', 'onu');

            return [
                'total' => (clone $query)->count(),
                'online' => (clone $query)->whereIn('onu_oper_status', ['online', 'active', 'up'])->count(),
                'offline' => (clone $query)->whereIn('onu_oper_status', ['offline', 'los', 'power_fail'])->count(),
                'unauthorized' => (clone $query)->whereIn('onu_oper_status', ['unauthorized', 'auth_fail', 'illegal'])->count(),
            ];
        });
    }

    private function key(int $oltId): string
    {
        return 'olt:'.$oltId.':onu_snapshot_v1';
    }
}
