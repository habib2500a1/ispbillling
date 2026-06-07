<?php

namespace App\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RedisException;
use Throwable;

final class ResilientHttpErrors
{
    public static function shouldRenderFriendlyPage(Throwable $e, Request $request): bool
    {
        if (config('app.debug') && app()->environment('local', 'testing')) {
            return false;
        }

        return self::isDeployMismatch($e)
            || self::isDatabaseConnectionFailure($e)
            || self::isRedisConnectionFailure($e)
            || self::isUnsupportedCacheDriver($e);
    }

    public static function isUnsupportedCacheDriver(Throwable $e): bool
    {
        if (! $e instanceof InvalidArgumentException) {
            return false;
        }

        return str_contains($e->getMessage(), 'Driver [failover] is not supported')
            || (str_contains($e->getMessage(), 'Driver [') && str_contains($e->getMessage(), '] is not supported.'));
    }

    public static function isDeployMismatch(Throwable $e): bool
    {
        return $e instanceof BindingResolutionException
            || ($e instanceof \Error && str_contains($e->getMessage(), 'Class "'));
    }

    public static function isDatabaseConnectionFailure(Throwable $e): bool
    {
        if (! $e instanceof QueryException) {
            return false;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'connection refused')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'connection timed out')
            || str_contains($message, 'no connection to the server');
    }

    public static function isRedisConnectionFailure(Throwable $e): bool
    {
        if ($e instanceof RedisException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'redis')
            && (
                str_contains($message, 'connection refused')
                || str_contains($message, 'went away')
                || str_contains($message, 'timed out')
            );
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function render(Throwable $e, Request $request)
    {
        Log::warning('resilient_http_error', [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'url' => $request->fullUrl(),
        ]);

        if ($request->is('livewire/update') || $request->is('livewire/upload-file')) {
            return response()->json(['components' => [], 'assets' => []], 200);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => self::userMessage($e),
            ], 503);
        }

        return response()->view('errors.maintenance', [
            'title' => 'Please wait…',
            'message' => self::userMessage($e),
            'autoRefresh' => true,
        ], 503);
    }

    public static function userMessage(Throwable $e): string
    {
        if (self::isUnsupportedCacheDriver($e)) {
            return 'Cache configuration needs a quick fix. Run isp:recover-site on the server, or set CACHE_STORE=redis in .env.';
        }

        if (self::isDeployMismatch($e)) {
            return 'A deploy update is still applying. Refresh this page in a few seconds.';
        }

        if (self::isDatabaseConnectionFailure($e)) {
            return 'Database is temporarily unavailable. Please retry shortly.';
        }

        if (self::isRedisConnectionFailure($e)) {
            return 'Session cache is reconnecting. Please refresh in a few seconds.';
        }

        return 'The system is busy. Please retry shortly.';
    }
}
