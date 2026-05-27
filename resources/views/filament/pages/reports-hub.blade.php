@php
    $stats = $this->getStats();
    $statCards = [
        ['label' => 'Collected (MTD)', 'value' => number_format($stats['collected'], 2).' BDT', 'hint' => $stats['collection_rate'].'% of invoiced', 'class' => 'isp-hub-stat--teal'],
        ['label' => 'Outstanding due', 'value' => number_format($stats['outstanding'], 2).' BDT', 'hint' => 'Open and partial invoices', 'class' => 'isp-hub-stat--danger', 'valueClass' => 'isp-hub-stat-value--danger'],
        ['label' => 'Active / online', 'value' => number_format($stats['active_subscribers']).' / '.number_format($stats['online_now']), 'hint' => 'Subscribers / live sessions', 'class' => 'isp-hub-stat--sky'],
        ['label' => 'New / churned', 'value' => '+'.$stats['new_subscribers'].' / -'.$stats['churned'], 'hint' => 'Current reporting period', 'class' => 'isp-hub-stat--amber'],
    ];
    $shortcutLinks = [
        ['eyebrow' => 'Analytics', 'label' => 'Analytics dashboard', 'hint' => 'All reports with date filter & charts', 'url' => \App\Filament\Pages\AnalyticsReports::getUrl(), 'icon' => 'heroicon-o-chart-bar', 'accent' => 'text-indigo-600'],
        ['eyebrow' => 'Collection', 'label' => 'Collection report', 'hint' => 'Payments by method & day', 'url' => \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'collection']), 'icon' => 'heroicon-o-banknotes', 'accent' => 'text-emerald-600'],
        ['eyebrow' => 'Payments', 'label' => 'Payment reports', 'hint' => 'Collections, discounts & export', 'url' => \App\Filament\Pages\PaymentsReport::getUrl(), 'icon' => 'heroicon-o-credit-card', 'accent' => 'text-sky-600'],
        ['eyebrow' => 'Due', 'label' => 'Due report pro', 'hint' => 'Aging buckets & totals', 'url' => \App\Filament\Pages\DueReportProPage::getUrl(), 'icon' => 'heroicon-o-exclamation-triangle', 'accent' => 'text-amber-600'],
        ['eyebrow' => 'Growth', 'label' => 'Subscriber growth', 'hint' => 'New vs active trend', 'url' => \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'growth']), 'icon' => 'heroicon-o-arrow-trending-up', 'accent' => 'text-violet-600'],
        ['eyebrow' => 'Export', 'label' => 'Print & export', 'hint' => 'CSV and print-friendly views', 'url' => \App\Filament\Pages\PrintReportsHub::getUrl(), 'icon' => 'heroicon-o-printer', 'accent' => 'text-slate-600'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Analytics workspace"
            title="Reporting & analytics"
            description="Collection · due · revenue · churn · subscriber growth · online users · area-wise · package popularity."
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">Multi-report center</span>
                    <span class="isp-hub-section__meta">Charts and exports</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <x-isp.hub-section-nav group="Reports" :hub-url="\App\Filament\Pages\ReportsHub::getUrl()" hub-label="Reports center" />

        <x-isp.hub-module-grid group="Reports" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Quick analytics shortcuts</h2>
                    <p class="isp-hub-section__desc">Open the most-used reporting views, export tools, and trend analysis pages faster.</p>
                </div>
                <span class="isp-hub-section__meta">{{ count($shortcutLinks) }} shortcuts</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--3">
                @foreach ($shortcutLinks as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon {{ $link['accent'] }}">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="isp-module-card__eyebrow">{{ $link['eyebrow'] }}</p>
                                <p class="isp-module-card__title">{{ $link['label'] }}</p>
                                <p class="isp-module-card__desc">{{ $link['hint'] }}</p>
                            </div>
                            <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
