<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Support\CompanyBranding;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(): View
    {
        $packages = Package::query()
            ->publicCatalog()
            ->orderBy('price_monthly')
            ->orderBy('download_mbps')
            ->get();

        return view('landing.index', [
            'company' => CompanyBranding::name(),
            'tagline' => config('isp.company_tagline'),
            'phone' => config('isp.company_phone'),
            'email' => config('isp.company_email'),
            'address' => config('isp.company_address'),
            'logo' => CompanyBranding::logoUrl(),
            'packages' => $packages,
            'adminUrl' => rtrim((string) config('app.url'), '/').'/admin',
            'payUrl' => url('/pay'),
            'portalUrl' => config('portal.enabled', true) ? route('portal.login') : null,
            'signupUrl' => config('portal.signup.enabled', true) ? route('portal.signup') : null,
        ]);
    }
}
