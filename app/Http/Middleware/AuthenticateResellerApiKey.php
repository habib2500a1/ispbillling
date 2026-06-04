<?php

namespace App\Http\Middleware;

use App\Models\ResellerApiKey;
use App\Services\Resellers\ResellerApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateResellerApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken()
            ?? $request->header('X-Reseller-Api-Key');

        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'API key required.'], 401);
        }

        $apiKey = ResellerApiKey::findByPlainKey($plain);
        if ($apiKey === null) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $reseller = $apiKey->reseller;
        if ($reseller === null || ! $reseller->is_active || ! $reseller->api_access_enabled) {
            return response()->json(['message' => 'Reseller account inactive or API disabled.'], 403);
        }

        $limit = $apiKey->rate_limit_per_minute ?? $reseller->api_rate_limit_per_minute ?? 120;
        $cacheKey = 'reseller_api_rl:'.$apiKey->id.':'.now()->format('YmdHi');
        $count = (int) Cache::get($cacheKey, 0);
        if ($count >= $limit) {
            return response()->json(['message' => 'Rate limit exceeded.'], 429);
        }
        Cache::put($cacheKey, $count + 1, now()->addMinute());

        $request->attributes->set('reseller_api_key', $apiKey);
        $request->attributes->set('reseller_api_reseller', $reseller);

        $start = microtime(true);
        $response = $next($request);
        $duration = (int) round((microtime(true) - $start) * 1000);

        app(ResellerApiKeyService::class)->logUsage(
            $apiKey,
            $request,
            $response->getStatusCode(),
            $duration,
        );

        return $response;
    }
}
