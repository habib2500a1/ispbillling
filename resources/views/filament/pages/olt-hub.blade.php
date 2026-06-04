@php
    $stats = $this->getStats();
    $onlinePct = ($stats['onus'] ?? 0) > 0
        ? round(100 * ($stats['onus_online'] ?? 0) / max(1, $stats['onus']))
        : 0;
    $topPon = $stats['top_pon_ports'] ?? [];
    $vendors = $stats['vendors'] ?? [];
@endphp

<x-filament-panels::page class="isp-olt-hub-page">
    <div class="olt-pro">
        <header class="olt-hero">
            <div class="olt-hero__grid">
                <span class="olt-hero__badge">
                    <span class="olt-hero__badge-dot" aria-hidden="true"></span>
                    GPON · optical NOC
                </span>
                <h1 class="olt-hero__title">OLT &amp; Optical Fiber</h1>
                <p class="olt-hero__sub">
                    Enterprise GPON NOC — OLT health (CPU/RAM/temp), PON port status, ONU online/offline, RX/TX optical power, temp/voltage, alarms, topology. Multi-vendor: Huawei, ZTE, VSOL, BDCOM, Fiberhome, C-DATA.
                </p>
                <div class="olt-hero__actions">
                    <a href="{{ \App\Filament\Resources\OltResource::getUrl() }}" class="olt-btn olt-btn--white">
                        <x-filament::icon icon="heroicon-m-server-stack" class="h-4 w-4" />
                        OLT list
                    </a>
                    <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl() }}" class="olt-btn olt-btn--glass">
                        <x-filament::icon icon="heroicon-m-light-bulb" class="h-4 w-4" />
                        Optical DB
                    </a>
                    <a href="{{ \App\Filament\Pages\NetworkTopology::getUrl() }}" class="olt-btn olt-btn--glass">
                        <x-filament::icon icon="heroicon-m-share" class="h-4 w-4" />
                        Topology
                    </a>
                </div>
            </div>
            <div class="olt-hero__live">
                <div class="olt-hero__live-card">
                    <span class="olt-hero__live-label">ONUs online</span>
                    <strong class="olt-hero__live-value">{{ number_format($stats['onus_online'] ?? 0) }}</strong>
                    <span class="olt-hero__live-hint">{{ $onlinePct }}% of {{ number_format($stats['onus'] ?? 0) }} ONUs · {{ number_format($stats['olts'] ?? 0) }} OLTs</span>
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

        @if ($topPon !== [] || $vendors !== [])
            <section class="olt-noc-panel">
                <div class="olt-section__head">
                    <h2 class="olt-section__title">NOC snapshot</h2>
                    <p class="olt-section__sub">Top utilized PON ports · registered OLT drivers</p>
                </div>
                <div class="olt-noc-panel__grid">
                    @if ($topPon !== [])
                        <div class="olt-noc-panel__card">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Top PON ports</h3>
                            <ul class="mt-2 space-y-2 text-sm">
                                @foreach ($topPon as $pon)
                                    <li class="border-b border-slate-100 pb-2 last:border-0 dark:border-slate-800" title="{{ $pon['mikrotik_detail'] ?? '' }} · {{ $pon['vlan_detail'] ?? '' }}">
                                        <div class="flex justify-between gap-2">
                                            <span class="font-medium">{{ $pon['olt_name'] }} · {{ $pon['port_display'] ?? $pon['port_index'] }}</span>
                                            <span class="font-mono text-xs tabular-nums">{{ $pon['onu_online'] }}/{{ $pon['onu_total'] }}</span>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-slate-500">
                                            MK: {{ $pon['mikrotik'] ?? '—' }} · VLAN: {{ $pon['vlan'] ?? '—' }} · {{ $pon['avg_rx_dbm'] ?? '—' }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="olt-noc-panel__card">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Multi-vendor support</h3>
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($this->getSupportedVendorLabels() as $label)
                                <li class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $label }}</li>
                            @endforeach
                        </ul>
                        @if ($vendors !== [])
                            <p class="mt-3 text-xs font-bold uppercase text-slate-500">Your fleet</p>
                            <ul class="mt-1 space-y-1 text-xs">
                                @foreach ($vendors as $driver => $count)
                                    <li class="flex justify-between"><span>{{ strtoupper(str_replace('_', ' ', (string) $driver)) }}</span><span class="font-mono">{{ $count }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        <section>
            <div class="olt-section__head">
                <h2 class="olt-section__title">Fiber tools</h2>
                <p class="olt-section__sub">Provision, monitor, map, and troubleshoot PON plant</p>
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

        <nav class="olt-dock" aria-label="Quick navigation">
            <div class="olt-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Resources\OltResource::getUrl(), 'label' => 'OLTs', 'icon' => 'heroicon-o-server-stack', 'active' => true],
                    ['url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(), 'label' => 'Optical', 'icon' => 'heroicon-o-light-bulb'],
                    ['url' => \App\Filament\Pages\FiberPlantMap::getUrl(), 'label' => 'Fiber map', 'icon' => 'heroicon-o-map'],
                    ['url' => \App\Filament\Pages\ManageOpticalLaserSettings::getUrl(), 'label' => 'Laser', 'icon' => 'heroicon-o-adjustments-vertical'],
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
