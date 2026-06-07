<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\Portal\PortalContentCatalog;
use App\Services\Portal\PortalMovieServerCatalog;
use App\Support\CompanyBranding;
use App\Support\MobileAppLinks;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class LandingPageController extends Controller
{
    public function __invoke(): View
    {
        try {
            $tenantId = TenantResolver::currentTenantId() ?? (int) config('inventory.default_tenant_id', 1);

            $payload = SafeCache::remember(
                'landing:page:'.$tenantId,
                now()->addMinutes((int) config('isp.landing_cache_minutes', 2)),
                fn (): array => $this->buildLandingPayload($tenantId),
            );
        } catch (\Throwable) {
            $payload = $this->buildLandingPayload(
                TenantResolver::currentTenantId() ?? (int) config('inventory.default_tenant_id', 1),
            );
        }

        return view('landing.index', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLandingPayload(int $tenantId): array
    {
        $packages = Package::query()
            ->publicCatalog()
            ->orderBy('price_monthly')
            ->orderBy('download_mbps')
            ->get();

        $movieServers = PortalMovieServerCatalog::forLanding();

        $shopEnabled = $this->shopHasActiveProducts($tenantId);

        return [
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
