<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as AuthenticateMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts Sanctum bearer tokens (mobile app) or partner API keys (rsk_…).
 */
class AuthenticateResellerApi
{
    public function __construct(
        private readonly AuthenticateResellerApiKey $apiKeyAuth,
        private readonly AuthenticateMiddleware $authenticate,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken()
            ?? $request->header('X-Reseller-Api-Key');

        if (is_string($plain) && str_starts_with($plain, 'rsk_')) {
            return $this->apiKeyAuth->handle($request, $next);
        }

        $reseller = $request->user();
        if ($reseller instanceof Reseller) {
            return $next($request);
        }

        $reseller = Auth::guard('sanctum')->user();
        if ($reseller instanceof Reseller) {
            $request->setUserResolver(static fn () => $reseller);

            return $next($request);
        }

        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $this->authenticate->handle($request, function (Request $req) use ($next): Response {
            $user = Auth::guard('sanctum')->user();
            if ($user instanceof Reseller) {
                $req->setUserResolver(static fn () => $user);
            }

            return $next($req);
        }, 'sanctum');
    }
}
