<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\Portal\PortalContentCatalog;
use App\Services\Portal\PortalMovieServerCatalog;
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

        $movieServers = PortalMovieServerCatalog::forLanding();

        return view('landing.index', [
            'portalNotices' => PortalContentCatalog::noticesForLanding(),
            'portalMarquee' => PortalContentCatalog::marqueeForLanding(),
            'company' => CompanyBranding::name(),
            'tagline' => config('isp.company_tagline'),
            'phone' => config('isp.company_phone'),
            'email' => config('isp.company_email'),
            'address' => config('isp.company_address'),
            'logo' => CompanyBranding::logoUrl(),
            'packages' => $packages,
            'movieServers' => $movieServers,
            'adminUrl' => rtrim((string) config('app.url'), '/').'/admin',
            'payUrl' => url('/pay'),
            'portalUrl' => config('portal.enabled', true) ? route('portal.login') : null,
            'signupUrl' => config('portal.signup.enabled', true) ? route('portal.signup') : null,
        ]);
    }
}
