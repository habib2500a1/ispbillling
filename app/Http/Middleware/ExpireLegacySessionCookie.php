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

        if ($request->is('admin/login') && $request->isMethod('GET')) {
            return ClearLegacySessionCookies::apply($response);
        }

        return $response;
    }
}

