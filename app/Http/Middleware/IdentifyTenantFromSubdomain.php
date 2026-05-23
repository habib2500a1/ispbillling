<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\Tenant\TenantScopedConfig;
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

        if (! Schema::hasTable('tenants')) {
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
                return redirect('/reseller/login');
            }
        }

        return $next($request);
    }
}
