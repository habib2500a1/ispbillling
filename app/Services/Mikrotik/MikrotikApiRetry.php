<?php

namespace App\Services\Mikrotik;

use Illuminate\Support\Facades\Log;

final class MikrotikApiRetry
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function attempt(callable $callback, ?int $maxAttempts = null): mixed
    {
        $maxAttempts = max(1, $maxAttempts ?? (int) config('mikrotik.api_max_attempts', 3));
        $delayMs = max(0, (int) config('mikrotik.retry_delay_ms', 400));
        $last = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $last = $e;
                if ($attempt >= $maxAttempts) {
                    break;
                }
                Log::debug('mikrotik.api_retry', [
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);
                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw $last;
    }
}
