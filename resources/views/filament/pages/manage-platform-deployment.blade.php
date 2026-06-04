@php
    $info = $this->getDeploymentInfo();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-isp.hub-hero
            eyebrow="Platform"
            title="Rent + Sell deployment"
            description="SaaS (rent) = no license lock. On-premise (sell) = signed ISP_LICENSE_KEY per domain."
            class="isp-hub-hero--violet"
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Mode</p>
                <p class="mt-1 text-lg font-bold">{{ $info['mode_label'] }}</p>
                <p class="mt-1 text-xs text-gray-500"><code>ISP_DEPLOYMENT_MODE={{ $info['mode'] }}</code></p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">License</p>
                <p class="mt-1 text-lg font-bold {{ $info['license_valid'] ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ $info['license_valid'] ? 'Valid' : 'Invalid / not required' }}
                </p>
                <p class="mt-1 text-xs text-gray-500">{{ $info['license_message'] }}</p>
            </div>
        </div>

        @if ($info['mode'] === 'saas')
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                <strong>Rent (SaaS):</strong> Keep <code>ISP_DEPLOYMENT_MODE=saas</code> and <code>ISP_LICENSE_ENFORCE=false</code> on your host (e.g. bill.flixbd.xyz). Multi-tenant + API HMAC per ISP.
            </div>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
                <strong>Sell (on-premise):</strong> Customer <code>.env</code> needs <code>ISP_LICENSE_KEY</code>. You sign keys with:
                <pre class="mt-2 overflow-x-auto rounded bg-black/10 p-2 text-xs">php artisan isp:license:generate-keys
php artisan isp:issue-license customer.example.com --expires=2027-12-31</pre>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-bold">Tenants</p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Active: <strong>{{ $info['tenant_count'] }}</strong>
                @if ($info['max_tenants'])
                    / max {{ $info['max_tenants'] }} (license)
                @endif
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <a href="{{ \App\Filament\Pages\ManageApiConfiguration::getUrl() }}" class="text-sm font-semibold text-primary-600 underline">
                API configuration (HMAC / REST)
            </a>
            <span class="text-gray-300">|</span>
            <span class="text-sm text-gray-500">Docs: <code>docs/PLATFORM_RENT_SELL_SECURITY.md</code></span>
        </div>
    </div>
</x-filament-panels::page>
