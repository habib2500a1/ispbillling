<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\Portal\PortalContentCatalog;
use App\Services\Portal\PortalMovieServerCatalog;
use App\Support\CompanyBranding;
use App\Support\MobileAppLinks;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

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

        $shopEnabled = $this->shopHasActiveProducts();

        return view('landing.index', [
            'shopUrl' => $shopEnabled ? route('shop.index') : null,
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
            'staffLoginUrl' => rtrim((string) config('app.url'), '/').'/admin/login',
            'payUrl' => url('/pay'),
            'loginHubUrl' => config('portal.enabled', true) ? route('login.hub') : null,
            'portalUrl' => config('portal.enabled', true) ? route('login.hub') : null,
            'customerLoginUrl' => config('portal.enabled', true) ? route('portal.login') : null,
            'resellerLoginUrl' => config('reseller_portal.enabled', true) ? url('/reseller/login') : null,
            'portalDashboardUrl' => config('portal.enabled', true) ? route('portal.dashboard') : null,
            'signupUrl' => config('portal.signup.enabled', true) ? route('portal.signup') : null,
            'appDownloadUrl' => MobileAppLinks::downloadUrl(),
        ]);
    }

    private function shopHasActiveProducts(): bool
    {
        if (! config('inventory.shop_enabled', true)) {
            return false;
        }

        if (! Schema::hasTable('products')) {
            return false;
        }

        try {
            return \App\Models\Product::withoutGlobalScopes()
                ->where('tenant_id', (int) config('inventory.default_tenant_id', 1))
                ->where('is_active', true)
                ->where('show_on_shop', true)
                ->where('stock_qty', '>', 0)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
