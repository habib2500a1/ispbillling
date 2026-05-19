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
            ->where('is_active', true)
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
            'adminUrl' => $this->adminUrl(),
            'payUrl' => url('/pay'),
            'portalUrl' => route('portal.login'),
        ]);
    }

    private function adminUrl(): string
    {
        $adminHost = config('domains.admin');
        if (filled($adminHost) && request()->getHost() !== $adminHost) {
            return 'https://'.$adminHost.'/admin';
        }

        return url('/admin');
    }
}
