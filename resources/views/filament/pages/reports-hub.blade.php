@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            title="Reporting & analytics"
            description="Collection · due · revenue · churn · subscriber growth · online users · area-wise · package popularity."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Collected (MTD)</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($stats['collected'], 2) }} BDT</p>
                <p class="text-xs text-gray-500">{{ $stats['collection_rate'] }}% of invoiced</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Outstanding due</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ number_format($stats['outstanding'], 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Active / online</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($stats['active_subscribers']) }} / {{ number_format($stats['online_now']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">New / churned (period)</p>
                <p class="mt-1 text-2xl font-bold"><span class="text-emerald-600">+{{ $stats['new_subscribers'] }}</span> <span class="text-rose-600">−{{ $stats['churned'] }}</span></p>
            </div>
        </div>

        <x-isp.hub-section-nav group="Reports" :hub-url="\App\Filament\Pages\ReportsHub::getUrl()" hub-label="Reports center" />

        <x-isp.hub-module-grid group="Reports" />

        <details class="isp-ops-details">
            <summary class="isp-ops-details-summary">Quick analytics shortcuts</summary>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold group-hover:text-indigo-600 dark:text-white">Analytics dashboard</p>
                <p class="mt-1 text-sm text-gray-500">All reports with date filter & charts</p>
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'collection']) }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Collection report</p>
                <p class="mt-1 text-sm text-gray-500">Payments by method & day</p>
            </a>
            <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}" class="group rounded-xl border border-blue-200 bg-blue-50/50 p-5 shadow-sm transition hover:border-blue-400 dark:border-blue-800 dark:bg-blue-950/30">
                <p class="font-semibold text-blue-900 dark:text-blue-100">Payment reports</p>
                <p class="mt-1 text-sm text-blue-800/80 dark:text-blue-300">Collections, discounts & export</p>
            </a>
            <a href="{{ \App\Filament\Pages\DueReportPage::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Due report</p>
                <p class="mt-1 text-sm text-gray-500">Outstanding invoices</p>
            </a>
            <a href="{{ \App\Filament\Pages\DueReportProPage::getUrl() }}" class="group rounded-xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm transition hover:border-amber-400 dark:border-amber-800 dark:bg-amber-950/30">
                <p class="font-semibold text-amber-900 dark:text-amber-100">Due report pro</p>
                <p class="mt-1 text-sm text-amber-800/80 dark:text-amber-300">Aging buckets & totals</p>
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'revenue']) }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Revenue analytics</p>
                <p class="mt-1 text-sm text-gray-500">12-month invoiced vs collected</p>
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'churn']) }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Churn analysis</p>
                <p class="mt-1 text-sm text-gray-500">Suspended / terminated</p>
            </a>
            <a href="{{ \App\Filament\Pages\ChurnZoneReports::getUrl() }}" class="group rounded-xl border border-cyan-200 bg-cyan-50/50 p-5 shadow-sm transition hover:border-cyan-400 dark:border-cyan-800 dark:bg-cyan-950/30">
                <p class="font-semibold text-cyan-900 dark:text-cyan-100">Churn & zone collection</p>
                <p class="mt-1 text-sm text-cyan-800/80 dark:text-cyan-300">Recovery by zone · churn heatmap</p>
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'growth']) }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Subscriber growth</p>
                <p class="mt-1 text-sm text-gray-500">New vs active trend</p>
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'online']) }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Online user report</p>
                <p class="mt-1 text-sm text-gray-500">Live PPP sessions</p>
            </a>
            <a href="{{ \App\Filament\Pages\AreaWiseClientsReport::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Area-wise clients</p>
                <p class="mt-1 text-sm text-gray-500">Subscribers & collections by area</p>
            </a>
            <a href="{{ \App\Filament\Pages\PackageWiseReportPage::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Package-wise report</p>
                <p class="mt-1 text-sm text-gray-500">Active subs & est. MRR</p>
            </a>
            <a href="{{ \App\Filament\Pages\ExportClientsReport::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Export clients</p>
                <p class="mt-1 text-sm text-gray-500">CSV download of all subscribers</p>
            </a>
            <a href="{{ \App\Filament\Pages\PrintReportsHub::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Print reports</p>
                <p class="mt-1 text-sm text-gray-500">Print-friendly PDF views</p>
            </a>
            <a href="{{ \App\Filament\Pages\BillingReports::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold dark:text-white">Monthly AR widgets</p>
                <p class="mt-1 text-sm text-gray-500">Legacy monthly stats & aging</p>
            </a>
            </div>
        </details>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
