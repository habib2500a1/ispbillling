@php
    $data = $this->getViewData();
    $kpis = $data['kpis'];
    $sections = $data['sections'];
@endphp

<x-filament-panels::page class="torg-page isp-hub-page">
    <link rel="stylesheet" href="{{ asset('css/tenant-org-hub.css') }}?v={{ @filemtime(public_path('css/tenant-org-hub.css')) ?: 1 }}">

    <div class="space-y-5">
        <section class="torg-hero torg-glass">
            <p class="text-xs uppercase tracking-wider opacity-80 mb-1">Configuration</p>
            <h1 class="torg-hero__title">ISP Setup Center</h1>
            <p class="torg-hero__sub">Areas · zones · packages · MikroTik · Bangladesh districts — quick links for new ISP onboarding.</p>
        </section>

        <div class="torg-kpi-grid">
            @foreach ($kpis as $card)
                <article class="torg-kpi-card">
                    <span>{{ $card['label'] }}</span>
                    <strong>{{ number_format($card['value']) }}</strong>
                </article>
            @endforeach
        </div>

        @foreach ($sections as $section)
            <section class="torg-glass p-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide opacity-80 mb-3">{{ $section['title'] }}</h2>
                <div class="torg-quick-grid">
                    @foreach ($section['links'] as $link)
                        <a href="{{ $link['url'] }}" class="torg-quick torg-quick--sky">
                            <x-filament::icon :icon="'heroicon-o-'.$link['icon']" class="h-5 w-5 shrink-0" />
                            <div>
                                <strong>{{ $link['label'] }}</strong>
                                <span>{{ $link['description'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="torg-glass p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide opacity-80 mb-2">ISPTrack migration</h2>
            <p class="text-sm opacity-80 mb-2">Export JSON from ISPTrack, then import in phases:</p>
            <pre class="text-xs bg-black/5 dark:bg-white/5 p-3 rounded-lg overflow-x-auto">php artisan isp:export-isptrack-json --output=storage/app/import/isptrack.json
php artisan isp:import-isptrack storage/app/import/isptrack.json --tenant=1 --dry-run
php artisan isp:import-isptrack storage/app/import/isptrack.json --tenant=1</pre>
        </section>
    </div>
</x-filament-panels::page>
