@php
    $links = [
        ['eyebrow' => 'Routers', 'label' => 'MikroTik servers', 'hint' => 'Credentials & sync', 'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('index'), 'icon' => 'heroicon-o-server'],
        ['eyebrow' => 'Sessions', 'label' => 'Online clients', 'hint' => 'Live PPPoE', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'icon' => 'heroicon-o-signal'],
        ['eyebrow' => 'Traffic', 'label' => 'Bandwidth monitor', 'hint' => 'Graphs & abuse', 'url' => \App\Filament\Pages\BandwidthMonitor::getUrl(), 'icon' => 'heroicon-o-chart-bar-square'],
    ];
    $statCards = $this->getStatCards();
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-network-noc-page">
    <div class="net-noc-pro space-y-5" wire:poll.visible.{{ \App\Support\PerformanceSettings::hubPollSeconds() }}s>
        <header class="net-hub-hero">
            <div>
                <span class="net-hub-hero__badge">
                    <span class="net-hub-hero__dot" aria-hidden="true"></span>
                    Router operations
                </span>
                <h1 class="net-hub-hero__title">MikroTik dashboard</h1>
                <p class="net-hub-hero__sub">
                    PPPoE sessions, router API status, traffic health, and fleet monitoring.
                </p>
                <div class="net-hub-actions">
                    <a href="{{ \App\Filament\Resources\MikrotikServerResource::getUrl() }}" class="net-hub-btn net-hub-btn--white">
                        <x-filament::icon icon="heroicon-m-server" class="h-4 w-4" />
                        Routers
                    </a>
                    <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="net-hub-btn net-hub-btn--glass">
                        <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                        Live PPP
                    </a>
                </div>
            </div>
        </header>

        <div class="net-hub-stats">
            @foreach ($statCards as $i => $card)
                @php
                    $tone = match ($i) {
                        0 => 'online',
                        1 => 'bandwidth',
                        2 => 'warning',
                        default => '',
                    };
                @endphp
                <article @class(['net-hub-stat', 'net-hub-stat--'.$tone => filled($tone)])>
                    <span class="net-hub-stat__label">{{ $card['label'] }}</span>
                    <strong class="net-hub-stat__value">{{ $card['value'] }}</strong>
                    @if (! empty($card['hint']))
                        <span style="font-size:0.68rem;color:var(--net-muted);">{{ $card['hint'] }}</span>
                    @endif
                </article>
            @endforeach
        </div>

        <section>
            <div class="net-hub-section__head" style="margin-bottom:0.75rem;">
                <h2 class="net-hub-section__title">Router actions</h2>
                <p class="net-hub-section__sub">MikroTik control, live PPP sessions, and bandwidth analytics</p>
            </div>
            <div class="net-hub-tiles">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="net-hub-tile">
                        <p style="margin:0;font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--net-muted);">{{ $link['eyebrow'] }}</p>
                        <h3 class="net-hub-tile__title">{{ $link['label'] }}</h3>
                        <p class="net-hub-tile__desc">{{ $link['hint'] }}</p>
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
