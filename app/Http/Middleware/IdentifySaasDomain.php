<?php

namespace App\Http\Middleware;

use App\Services\Saas\SaasContext;
use App\Services\Saas\SaasDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifySaasDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        SaasContext::rememberHostOperator(SaasDomain::findByHost($request->getHost()));

        return $next($request);
    }
}
