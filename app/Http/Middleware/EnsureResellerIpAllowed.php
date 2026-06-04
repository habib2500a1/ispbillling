<?php

namespace App\Http\Middleware;

use App\Services\Resellers\ResellerPortalLoginLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerIpAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $reseller = auth('reseller')->user();
        if ($reseller === null) {
            return $next($request);
        }

        $logger = app(ResellerPortalLoginLogger::class);
        if (! $logger->isIpAllowed($reseller, $request->ip())) {
            auth('reseller')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('reseller.login')
                ->withErrors(['login' => 'Access denied from this IP address.']);
        }

        return $next($request);
    }
}
