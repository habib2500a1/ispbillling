<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpireLegacySessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Ensure old session cookie is actively removed from browsers.
        if ($request->cookies->has('ispplatform_admin_session')) {
            $response->headers->clearCookie(
                'ispplatform_admin_session',
                '/',
                config('session.domain'),
                true,
                true,
                false,
                'lax',
            );
        }

        return $response;
    }
}

