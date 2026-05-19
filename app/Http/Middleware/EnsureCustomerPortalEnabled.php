<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('portal.enabled', true)) {
            return $next($request);
        }

        if (Auth::guard('customer')->check()) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Customer portal is temporarily unavailable. Please pay your bill at /pay or contact support.'),
            ], 503);
        }

        return redirect()
            ->to(url('/pay'))
            ->with('portal_disabled', true);
    }
}
