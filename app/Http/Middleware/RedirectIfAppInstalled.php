<?php

namespace App\Http\Middleware;

use App\Support\AppInstalled;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RedirectIfAppInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AppInstalled::isInstalled()) {
            return $next($request);
        }

        return redirect('/admin');
    }
}
