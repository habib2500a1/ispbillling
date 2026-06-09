<?php

namespace App\Http\Middleware;

use App\Support\ClearLegacySessionCookies;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpireLegacySessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('session.legacy_cleanup', true)
            || trim((string) config('session.legacy_domain', '')) === '') {
            return $response;
        }

        // Only on login form GET — never on /admin/login/complete (session must persist).
        if ($request->isMethod('GET') && (
            $request->is('login')
            || $request->is('admin/login')
        )) {
            return ClearLegacySessionCookies::apply($response);
        }

        return $response;
    }
}

