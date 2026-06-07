<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\Tenant\TenantScopedConfig;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantFromSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantResolver::setSubdomainTenantId(null);

        try {
            if (! SafeCache::remember('bootstrap.tenants_table', 300, fn (): bool => Schema::hasTable('tenants'))) {
                return $next($request);
            }

            $landingTenantId = $this->resolveLandingHostTenantId($request);
            if ($landingTenantId !== null) {
                TenantResolver::setSubdomainTenantId($landingTenantId);
                TenantScopedConfig::apply($landingTenantId);

                return $next($request);
            }

            $base = strtolower(trim((string) config('isp.tenant_base_domain', '')));
            if ($base === '') {
                return $next($request);
            }

            $host = strtolower($request->getHost());
            if ($host === $base || ! str_ends_with($host, '.'.$base)) {
                return $next($request);
            }

            $sub = substr($host, 0, strlen($host) - strlen($base) - 1);
            if ($sub === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $sub)) {
                return $next($request);
            }

            $tenant = Tenant::query()->where('slug', $sub)->where('is_active', true)->first();
            if ($tenant) {
                TenantResolver::setSubdomainTenantId((int) $tenant->id);
                TenantScopedConfig::apply((int) $tenant->id);

                return $next($request);
            }

            $reseller = Reseller::query()
                ->withoutGlobalScopes()
                ->where('portal_subdomain', $sub)
                ->where('white_label_enabled', true)
                ->where('is_active', true)
                ->first();

            if ($reseller !== null) {
                TenantResolver::setSubdomainTenantId((int) $reseller->tenant_id);
                TenantScopedConfig::apply((int) $reseller->tenant_id);
                app()->instance('reseller.white_label', $reseller);

                if ($request->is('/') && ! $request->is('reseller*')) {
                    return redirect('/login');
                }
            }
        } catch (\Throwable) {
            // Never take down the whole site when tenant lookup fails.
        }

        return $next($request);
    }

    private function resolveLandingHostTenantId(Request $request): ?int
    {
        $defaultTenantId = (int) config('isp.default_tenant_id', 0);
        if ($defaultTenantId <= 0) {
            return null;
        }

        $host = strtolower($request->getHost());
        $candidates = \App\Support\TrustedAppUrl::allowedHosts();

        if (! in_array($host, $candidates, true)) {
            return null;
        }

        return $defaultTenantId;
    }
}
