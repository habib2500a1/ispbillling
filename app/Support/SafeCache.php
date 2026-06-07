<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class SafeCache
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function remember(string $key, mixed $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $callback);
        } catch (\Throwable $primary) {
            Log::channel('single')->warning('safe_cache.primary_failed', [
                'key' => $key,
                'message' => $primary->getMessage(),
            ]);

            try {
                return Cache::store('file')->remember($key, $ttl, $callback);
            } catch (\Throwable $fallback) {
                Log::channel('single')->warning('safe_cache.file_failed', [
                    'key' => $key,
                    'message' => $fallback->getMessage(),
                ]);

                return $callback();
            }
        }
    }

    public static function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable) {
            // ignore
        }

        try {
            Cache::store('file')->forget($key);
        } catch (\Throwable) {
            // ignore
        }
    }
}
