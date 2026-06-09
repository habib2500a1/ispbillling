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

        if ($request->isMethod('GET') && (
            $request->is('login')
            || $request->is('admin/login')
            || $request->is('admin/login/complete')
        )) {
            return ClearLegacySessionCookies::apply($response);
        }

        return $response;
    }
}

