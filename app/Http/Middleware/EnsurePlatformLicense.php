<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformLicense
{
    public function __construct(
        private readonly PlatformLicenseService $license,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->license->isEnforced()) {
            return $next($request);
        }

        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        $host = $request->getHost();
        $check = $this->license->validate($host);

        if ($check['valid']) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Platform license invalid.',
                'detail' => $check['message'],
            ], 503);
        }

        return response()->view('errors.license', [
            'message' => $check['message'],
            'deployment' => $this->license->deploymentMode(),
        ], 503);
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->is('up', 'health', 'health/*')) {
            return true;
        }

        if ($request->is('api/webhooks/*', 'webhooks/*', 'piprapay/webhook')) {
            return true;
        }

        return false;
    }
}
