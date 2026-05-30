<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Support\ResellerBranding;
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
        ResellerBranding::capturePartnerFromRequest($request);

        $branding = ResellerBranding::forCustomer(ResellerBranding::customerFromContext());

        View::share([
            'companyName' => $branding['companyName'],
            'companyLogo' => $branding['companyLogo'],
            'companyTagline' => $branding['companyTagline'],
            'companyPhone' => $branding['companyPhone'],
            'companyAddress' => $branding['companyAddress'] ?? '',
        ]);

        if ($branding['whiteLabelReseller'] instanceof Reseller) {
            View::share('whiteLabelReseller', $branding['whiteLabelReseller']);
        }

        if (filled($branding['whiteLabelPrimaryColor'])) {
            View::share('whiteLabelPrimaryColor', $branding['whiteLabelPrimaryColor']);
        }

        return $next($request);
    }
}
