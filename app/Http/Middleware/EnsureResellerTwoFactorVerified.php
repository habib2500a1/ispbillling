<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerTwoFactorVerified
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $reseller = $request->user('reseller');

        if ($reseller === null) {
            return redirect()->route('reseller.login');
        }

        if ($reseller->requiresTwoFactor() && ! $request->session()->get('reseller.2fa_passed')) {
            if (! $request->routeIs('reseller.two-factor.*')) {
                return redirect()->route('reseller.two-factor.challenge');
            }
        }

        return $next($request);
    }
}
