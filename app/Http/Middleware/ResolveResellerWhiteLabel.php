<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveResellerWhiteLabel
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partner = app()->bound('reseller.white_label') ? app('reseller.white_label') : null;

        if ($partner instanceof Reseller) {
            View::share('whiteLabelReseller', $partner);
            if ($partner->brand_primary_color) {
                View::share('whiteLabelPrimaryColor', $partner->brand_primary_color);
            }
        }

        return $next($request);
    }
}
