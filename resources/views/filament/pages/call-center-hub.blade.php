@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Call center"
            title="Call center"
            description="Call logs, follow-ups, SIP/WebSIP settings, and voice templates — Sheba-Fi parity workspace."
            class="isp-hub-hero--amber"
        />

        @if (auth()->check() && \App\Support\WebSipFeature::isEnabledForUser(auth()->user()))
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/40">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">লাইভ কল (WebSIP)</p>
                    <p class="text-xs text-emerald-800/80 dark:text-emerald-200/80">নম্বর দিয়ে কল করুন — PortSIP-এর মতো একই SIP লগইন।</p>
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-md hover:bg-emerald-700"
                    onclick="window.ispWebSipLiveCall && window.ispWebSipLiveCall('')"
                >
                    <x-filament::icon icon="heroicon-m-phone" class="h-5 w-5" />
                    কল করুন
                </button>
                <a
                    href="{{ \App\Filament\Pages\ManageCallCenterSettings::getUrl() }}"
                    class="text-xs font-semibold text-emerald-700 underline dark:text-emerald-300"
                >SIP settings</a>
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Calls today</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['calls_today']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pending follow-ups</p>
                <p class="mt-1 text-2xl font-bold text-amber-600">{{ number_format($stats['pending_followups']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Missed today</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ number_format($stats['missed_today']) }}</p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->getModules() as $module)
                <a href="{{ $module['url'] }}" class="isp-module-card group">
                    <div class="flex items-start gap-3">
                        <span class="isp-module-icon text-amber-600">
                            <x-filament::icon :icon="$module['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="isp-module-card__title">{{ $module['label'] }}</p>
                            <p class="isp-module-card__desc">{{ $module['description'] }}</p>
                        </div>
                        <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-300">
            <strong>PBX webhook:</strong> POST <code class="text-xs">/api/webhooks/call-center</code> with header
            <code class="text-xs">X-ISP-Webhook-Secret</code>. See <code>docs/CALL_CENTER_ARCHITECTURE.md</code>.
        </div>

    </div>
</x-filament-panels::page>
