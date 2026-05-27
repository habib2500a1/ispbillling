@php
    $links = [
        ['eyebrow' => 'Monitor', 'label' => 'Online clients', 'hint' => 'Live PPPoE table', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'icon' => 'heroicon-o-signal'],
        ['eyebrow' => 'GPON', 'label' => 'Optical hub', 'hint' => 'ONU signal & fiber', 'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(), 'icon' => 'heroicon-o-cpu-chip'],
        ['eyebrow' => 'Routers', 'label' => 'MikroTik servers', 'hint' => 'API status', 'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('index'), 'icon' => 'heroicon-o-server'],
        ['eyebrow' => 'Outages', 'label' => 'Outage board', 'hint' => 'Area incidents', 'url' => \App\Filament\Resources\OutageResource::getUrl('index'), 'icon' => 'heroicon-o-megaphone'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Network operations"
            title="NOC dashboard"
            description="Live network health — sessions, bandwidth, routers, OLT/ONU and fiber alerts. Auto-refreshes every 30 seconds."
            class="isp-hub-hero--cyan"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Quick actions</h2>
                    <p class="isp-hub-section__desc">Jump straight into live network monitoring, optical diagnostics, router health, and outage response.</p>
                </div>
                <span class="isp-hub-section__meta">Refresh 30s</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--4">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon text-cyan-600">
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

        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="['default' => 1, 'lg' => 2]"
        />
    </div>
</x-filament-panels::page>
