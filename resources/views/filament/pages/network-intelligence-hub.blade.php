@php
    $stats = $this->getStats();
    $onlinePct = ($stats['onus'] ?? 0) > 0
        ? round(100 * ($stats['onus_online'] ?? 0) / max(1, $stats['onus']))
        : 0;
    $snmpOk = $stats['snmp_available'] ?? false;
    $pollOk = $stats['last_poll_ok'];
    $netflowOn = $stats['netflow_enabled'] ?? false;
@endphp

<x-filament-panels::page class="isp-network-hub-page">
    <div class="net-pro olt-pro">
        <header class="net-hero olt-hero">
            <div class="olt-hero__grid">
                <span class="olt-hero__badge">
                    <span @class([
                        'olt-hero__badge-dot',
                        'net-hero__badge-dot--warn' => ! $snmpOk || $pollOk === false,
                    ]) aria-hidden="true"></span>
                    SNMP · NetFlow · NOC
                </span>
                <h1 class="olt-hero__title">Network Intelligence</h1>
                <p class="olt-hero__sub">
                    MikroTik routing, GPON optical polling, SNMP health, NetFlow traffic analysis, and live PPP sessions — unified network operations center.
                </p>
                <div class="net-health">
                    <span @class(['net-health__pill', 'net-health__pill--ok' => $snmpOk, 'net-health__pill--warn' => ! $snmpOk])>
                        SNMP {{ $snmpOk ? 'ready' : 'missing' }}
                    </span>
                    <span @class(['net-health__pill', 'net-health__pill--ok' => $netflowOn, 'net-health__pill--muted' => ! $netflowOn])>
                        NetFlow {{ $netflowOn ? 'enabled' : 'disabled' }}
                    </span>
                    @if ($pollOk === true)
                        <span class="net-health__pill net-health__pill--ok">Poll OK</span>
                    @elseif ($pollOk === false)
                        <span class="net-health__pill net-health__pill--danger">Poll failed</span>
                    @endif
                    @if ($stats['last_poll'])
                        <span class="net-health__pill net-health__pill--muted">Last poll {{ $stats['last_poll'] }}</span>
                    @endif
                </div>
                <div class="olt-hero__actions">
                    <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="olt-btn olt-btn--white">
                        <x-filament::icon icon="heroicon-m-bolt" class="h-4 w-4" />
                        Live PPP
                    </a>
                    <a href="{{ \App\Filament\Pages\SnmpMonitor::getUrl() }}" class="olt-btn olt-btn--glass">
                        <x-filament::icon icon="heroicon-m-signal" class="h-4 w-4" />
                        SNMP
                    </a>
                    <a href="{{ \App\Filament\Pages\NetflowAnalysis::getUrl() }}" class="olt-btn olt-btn--glass">
                        <x-filament::icon icon="heroicon-m-chart-bar" class="h-4 w-4" />
                        NetFlow
                    </a>
                    @if (\App\Filament\Pages\OltHub::canAccess())
                        <a href="{{ \App\Filament\Pages\OltHub::getUrl() }}" class="olt-btn olt-btn--glass">
                            <x-filament::icon icon="heroicon-m-server-stack" class="h-4 w-4" />
                            OLT center
                        </a>
                    @endif
                </div>
            </div>
            <div class="olt-hero__live">
                <div class="olt-hero__live-card">
                    <span class="olt-hero__live-label">ONUs online</span>
                    <strong class="olt-hero__live-value">{{ number_format($stats['onus_online'] ?? 0) }}</strong>
                    <span class="olt-hero__live-hint">
                        {{ $onlinePct }}% of {{ number_format($stats['onus'] ?? 0) }} ONUs
                        · {{ number_format($stats['mikrotik'] ?? 0) }} MikroTik
                    </span>
                </div>
            </div>
        </header>

        <div class="olt-stats">
            @foreach ($this->getKpiCards() as $kpi)
                <a href="{{ $kpi['url'] }}" class="olt-stat olt-stat--{{ $kpi['tone'] }}">
                    <div class="olt-stat__row">
                        <span class="olt-stat__icon">
                            <x-filament::icon :icon="$kpi['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <span class="olt-stat__label">{{ $kpi['label'] }}</span>
                    <strong class="olt-stat__value">{{ $kpi['value'] }}</strong>
                    <span class="olt-stat__hint">{{ $kpi['hint'] }}</span>
                </a>
            @endforeach
        </div>

        <section>
            <div class="olt-section__head">
                <h2 class="olt-section__title">Network tools</h2>
                <p class="olt-section__sub">Routers, monitoring, traffic analysis, IPAM, and infrastructure</p>
            </div>
            <div class="olt-bento">
                @foreach ($this->getActionCards() as $card)
                    <a
                        href="{{ $card['url'] }}"
                        @class([
                            'olt-tile olt-tile--' . $card['tone'],
                            'olt-tile--featured' => ! empty($card['featured']),
                        ])
                    >
                        <div class="olt-tile__head">
                            <span class="olt-tile__icon">
                                <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-up-right" class="olt-tile__go" />
                        </div>
                        <div>
                            <h3 class="olt-tile__title">{{ $card['title'] }}</h3>
                            <p class="olt-tile__desc">{{ $card['desc'] }}</p>
                        </div>
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

        <nav class="olt-dock" aria-label="Network quick navigation">
            <div class="olt-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Resources\MikrotikServerResource::getUrl(), 'label' => 'MikroTik', 'icon' => 'heroicon-o-server'],
                    ['url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl(), 'label' => 'Live', 'icon' => 'heroicon-o-bolt'],
                    ['url' => \App\Filament\Pages\SnmpMonitor::getUrl(), 'label' => 'SNMP', 'icon' => 'heroicon-o-signal'],
                    ['url' => \App\Filament\Pages\NetflowAnalysis::getUrl(), 'label' => 'Flow', 'icon' => 'heroicon-o-arrows-right-left', 'active' => true],
                ] as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'olt-dock__link',
                            'olt-dock__link--active' => ! empty($link['active']),
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
