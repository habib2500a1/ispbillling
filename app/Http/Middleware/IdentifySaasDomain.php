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

        if ($request->hasSession()) {
            $payTenant = (int) $request->session()->get('public_pay_operator_id');
            if ($payTenant > 0 && ! SaasContext::hostOperator()) {
                SaasContext::forceTenant($payTenant);
            }
        }

        return $next($request);
    }
}
