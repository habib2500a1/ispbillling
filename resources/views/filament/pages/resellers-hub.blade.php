@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <div class="rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 via-white to-violet-50/40 p-6 shadow-sm dark:border-primary-900/40 dark:from-primary-950/50 dark:via-gray-900 dark:to-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Reseller & franchise management</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                Reseller dashboard · commission on payments · territory (area/zone) · sub-resellers · revenue sharing · wallet transfers · white-label branding.
            </p>
            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">
                    <span class="text-primary-600 dark:text-primary-400">{{ $stats['total'] }}</span> partners
                </span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">
                    {{ $stats['active'] }} active
                </span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">
                    {{ $stats['franchises'] }} franchises
                </span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">
                    {{ $stats['white_label'] }} white-label
                </span>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Total wallet balance</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['wallet_total'], 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-5 dark:border-amber-900/50 dark:bg-amber-950/20">
                <p class="text-sm text-amber-800 dark:text-amber-300">Pending commission</p>
                <p class="mt-1 text-2xl font-bold text-amber-950 dark:text-amber-100">{{ number_format($stats['pending_commission'], 2) }} BDT</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Resources\ResellerResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white">All resellers & franchises</p>
                <p class="mt-1 text-sm text-gray-500">Create, edit, hierarchy, territories.</p>
            </a>
            <a href="{{ \App\Filament\Resources\ResellerResource::getUrl('create') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Add partner</p>
                <p class="mt-1 text-sm text-gray-500">Reseller, franchise, or sub-reseller.</p>
            </a>
            <a href="{{ \App\Filament\Resources\AreaResource::getUrl('index') }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Territory (areas & zones)</p>
                <p class="mt-1 text-sm text-gray-500">Assign coverage per partner.</p>
            </a>
            <a href="{{ url('/reseller/login') }}" target="_blank" rel="noopener" class="group rounded-xl border border-indigo-200 bg-indigo-50/60 p-5 shadow-sm transition hover:border-indigo-400 dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="font-semibold text-indigo-900 group-hover:text-indigo-700 dark:text-indigo-100">Partner portal</p>
                <p class="mt-1 text-sm text-indigo-700/80 dark:text-indigo-300">/reseller/login — subscribers, wallet & commissions.</p>
            </a>
        </div>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
