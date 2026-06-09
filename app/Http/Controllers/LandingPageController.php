<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\Portal\PortalContentCatalog;
use App\Services\Portal\PortalMovieServerCatalog;
use App\Support\CompanyBranding;
use App\Support\MobileAppLinks;
use App\Support\PublicTenantContext;
use App\Support\SafeCache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Response;
use Throwable;

class LandingPageController extends Controller
{
    public function __invoke(): Response
    {
        try {
            $tenantId = PublicTenantContext::tenantId();

            $payload = SafeCache::remember(
                'landing:page:'.$tenantId,
                now()->addMinutes((int) config('isp.landing_cache_minutes', 2)),
                fn (): array => $this->buildLandingPayload($tenantId),
            );
        } catch (\Throwable) {
            $payload = $this->buildLandingPayload(PublicTenantContext::tenantId());
        }

        return response()
            ->view('landing.index', $payload)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLandingPayload(int $tenantId): array
    {
        $packages = Package::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->publicCatalog()
            ->orderBy('price_monthly')
            ->orderBy('download_mbps')
            ->get();

        $movieServers = PortalMovieServerCatalog::forLanding();

        $shopOpen = config('inventory.shop_enabled', true);

        return [
            'shopUrl' => $shopOpen ? route('shop.index') : null,
            'shopHasProducts' => $shopOpen && $this->shopHasActiveProducts($tenantId),
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
        ];
    }

    private function shopHasActiveProducts(int $tenantId): bool
    {
        if (! config('inventory.shop_enabled', true)) {
            return false;
        }

        if (! Schema::hasTable('products')) {
            return false;
        }

        try {
            return \App\Models\Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('show_on_shop', true)
                ->where('stock_qty', '>', 0)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
