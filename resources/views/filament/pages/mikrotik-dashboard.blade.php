<x-filament-panels::page>
    @php
        $links = [
            ['eyebrow' => 'Routers', 'label' => 'MikroTik servers', 'hint' => 'Credentials & sync', 'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('index'), 'icon' => 'heroicon-o-server'],
            ['eyebrow' => 'Sessions', 'label' => 'Online clients', 'hint' => 'Live PPPoE', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'icon' => 'heroicon-o-signal'],
            ['eyebrow' => 'Traffic', 'label' => 'Bandwidth monitor', 'hint' => 'Graphs & abuse', 'url' => \App\Filament\Pages\BandwidthMonitor::getUrl(), 'icon' => 'heroicon-o-chart-bar-square'],
        ];
    @endphp

    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Router operations"
            title="MikroTik dashboard"
            description="PPPoE sessions, router API status, traffic and queue health."
            class="isp-hub-hero--slate"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Router actions</h2>
                    <p class="isp-hub-section__desc">Access MikroTik server control, live PPP sessions, and bandwidth analytics from the same view.</p>
                </div>
                <span class="isp-hub-section__meta">Router live</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--3">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon">
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
