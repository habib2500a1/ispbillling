@php
    $stats = $this->getStats();
    $fleet = $this->getRouterFleetStats();
    $onlinePct = ($stats['onus'] ?? 0) > 0
        ? round(100 * ($stats['onus_online'] ?? 0) / max(1, $stats['onus']))
        : 0;
    $snmpOk = $stats['snmp_available'] ?? false;
    $pollOk = $stats['last_poll_ok'];
    $netflowOn = $stats['netflow_enabled'] ?? false;
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-network-noc-page">
    <div class="net-noc-pro net-pro olt-pro space-y-5">
        <header class="net-hub-hero">
            <div>
                <span class="net-hub-hero__badge">
                    <span @class(['net-hub-hero__dot', 'net-hub-hero__dot--warn' => ! $snmpOk || $pollOk === false]) aria-hidden="true"></span>
                    Network Operations Center
                </span>
                <h1 class="net-hub-hero__title">Network Intelligence</h1>
                <p class="net-hub-hero__sub">
                    MikroTik routing, GPON optical polling, SNMP health, NetFlow traffic analysis, and live PPP sessions — unified NOC.
                </p>
                <div class="net-hub-pills">
                    <span class="net-hub-pill">SNMP {{ $snmpOk ? 'ready' : 'missing' }}</span>
                    <span class="net-hub-pill">NetFlow {{ $netflowOn ? 'on' : 'off' }}</span>
                    @if ($pollOk === true)
                        <span class="net-hub-pill">Poll OK</span>
                    @elseif ($pollOk === false)
                        <span class="net-hub-pill">Poll failed</span>
                    @endif
                    @if ($stats['last_poll'])
                        <span class="net-hub-pill">Last poll {{ $stats['last_poll'] }}</span>
                    @endif
                </div>
                <div class="net-hub-actions">
                    <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="net-hub-btn net-hub-btn--white">
                        <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                        Live PPP
                    </a>
                    <a href="{{ \App\Filament\Resources\MikrotikServerResource::getUrl() }}" class="net-hub-btn net-hub-btn--glass">
                        <x-filament::icon icon="heroicon-m-server" class="h-4 w-4" />
                        Routers
                    </a>
                    <a href="{{ \App\Filament\Pages\SnmpMonitor::getUrl() }}" class="net-hub-btn net-hub-btn--glass">
                        <x-filament::icon icon="heroicon-m-signal" class="h-4 w-4" />
                        SNMP
                    </a>
                    <a href="{{ \App\Filament\Pages\NetflowAnalysis::getUrl() }}" class="net-hub-btn net-hub-btn--glass">
                        <x-filament::icon icon="heroicon-m-chart-bar" class="h-4 w-4" />
                        NetFlow
                    </a>
                </div>
            </div>
            <div class="net-hub-hero__live">
                <span style="font-size:0.68rem;opacity:0.85;text-transform:uppercase;letter-spacing:0.06em;">Routers online</span>
                <strong>{{ number_format($fleet['online']) }}/{{ number_format($fleet['total']) }}</strong>
                <span style="font-size:0.78rem;opacity:0.85;display:block;margin-top:0.35rem;">
                    {{ number_format($stats['onus_online'] ?? 0) }} ONUs up · {{ number_format($stats['mikrotik'] ?? 0) }} MikroTik
                </span>
            </div>
        </header>

        <div class="net-hub-stats">
            <a href="{{ \App\Filament\Resources\MikrotikServerResource::getUrl() }}" class="net-hub-stat net-hub-stat--online">
                <span class="net-hub-stat__label">Routers online</span>
                <strong class="net-hub-stat__value">{{ number_format($fleet['online']) }}</strong>
            </a>
            <a href="{{ \App\Filament\Resources\MikrotikServerResource::getUrl() }}" class="net-hub-stat net-hub-stat--offline">
                <span class="net-hub-stat__label">Routers offline</span>
                <strong class="net-hub-stat__value">{{ number_format($fleet['offline']) }}</strong>
            </a>
            <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="net-hub-stat net-hub-stat--bandwidth">
                <span class="net-hub-stat__label">MikroTik fleet</span>
                <strong class="net-hub-stat__value">{{ number_format($fleet['total']) }}</strong>
            </a>
            <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::canAccess() ? \App\Filament\Pages\OpticalMonitoringHub::getUrl() : \App\Filament\Pages\SnmpMonitor::getUrl() }}" class="net-hub-stat net-hub-stat--warning">
                <span class="net-hub-stat__label">ONUs online</span>
                <strong class="net-hub-stat__value">{{ number_format($stats['onus_online'] ?? 0) }}</strong>
            </a>
        </div>

        <section>
            <div class="net-hub-section__head" style="margin-bottom:0.75rem;">
                <h2 class="net-hub-section__title">Network tools</h2>
                <p class="net-hub-section__sub">Routers, monitoring, traffic analysis, IPAM, and infrastructure</p>
            </div>
            <div class="net-hub-tiles">
                @foreach ($this->getActionCards() as $card)
                    <a
                        href="{{ $card['url'] }}"
                        @class([
                            'net-hub-tile',
                            'net-hub-tile--featured' => ! empty($card['featured']),
                        ])
                    >
                        <h3 class="net-hub-tile__title">{{ $card['title'] }}</h3>
                        <p class="net-hub-tile__desc">{{ $card['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="net-automation">
            <div class="net-automation__head">
                <div>
                    <h2 class="olt-section__title">Automation &amp; ingest</h2>
                    <p class="olt-section__sub">Scheduled polls, NetFlow pipelines, and ONU metadata sync commands</p>
                </div>
                <span class="net-automation__status">
                    <span class="net-automation__status-dot" aria-hidden="true"></span>
                    Automation ready
                </span>
            </div>
            <div class="net-automation__grid">
                @foreach ($this->getAutomationItems() as $item)
                    <article class="net-cmd net-cmd--{{ $item['tone'] }}">
                        <div class="net-cmd__top">
                            <span class="net-cmd__tag">{{ $item['tag'] }}</span>
                        </div>
                        <code class="net-cmd__code">{{ $item['command'] }}</code>
                        <p class="net-cmd__desc">{{ $item['desc'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <nav class="net-dock net-dock--mobile-only" aria-label="Network quick navigation">
            <div class="net-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Resources\MikrotikServerResource::getUrl(), 'label' => 'Routers', 'icon' => 'heroicon-o-server'],
                    ['url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'label' => 'Live', 'icon' => 'heroicon-o-bolt'],
                    ['url' => \App\Filament\Pages\SnmpMonitor::getUrl(), 'label' => 'SNMP', 'icon' => 'heroicon-o-signal'],
                    ['url' => \App\Filament\Pages\NetflowAnalysis::getUrl(), 'label' => 'Flow', 'icon' => 'heroicon-o-arrows-right-left', 'active' => true],
                ] as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'net-dock__link',
                            'net-dock__link--active' => ! empty($link['active']),
                        ])
                    >
                        <x-filament::icon :icon="$link['icon']" />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
