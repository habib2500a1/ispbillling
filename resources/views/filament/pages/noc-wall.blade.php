@php
    $d = $this->getWallData();
    $n = $d['noc'];
    $g = $d['gpon'];
    $s = $d['support'];

    $fmt = static fn ($value, int $decimals = 0): string => number_format((float) $value, $decimals);
    $trend = $n['bandwidth_trend'] ?? ['labels' => [], 'download_mbps' => [], 'upload_mbps' => []];
    $trendValues = [];

    foreach (($trend['download_mbps'] ?? []) as $index => $download) {
        $trendValues[] = max((float) $download, (float) ($trend['upload_mbps'][$index] ?? 0));
    }

    $trendPeak = max($trendValues ?: [1]);
    $trendBars = [];

    foreach ($trendValues as $index => $value) {
        $trendBars[] = [
            'label' => $trend['labels'][$index] ?? '',
            'height' => max(14, (int) round(($value / $trendPeak) * 100)),
            'download' => $trend['download_mbps'][$index] ?? 0,
            'upload' => $trend['upload_mbps'][$index] ?? 0,
        ];
    }

    $telemetry = $n['access_telemetry'] ?? ['current' => [], 'trend' => ['labels' => [], 'ping_loss_percent' => [], 'pon_module_temp_c' => [], 'sfp_temp_c' => []], 'sfp_is_fallback' => true];
    $telemetryCurrent = $telemetry['current'] ?? [];
    $telemetryTrend = $telemetry['trend'] ?? ['labels' => [], 'ping_loss_percent' => [], 'pon_module_temp_c' => [], 'sfp_temp_c' => []];
    $ponTrendPeak = max(array_map(static fn ($value) => is_numeric($value) ? (float) $value : 0.0, $telemetryTrend['pon_module_temp_c'] ?? []) ?: [80]);
    $sfpTrendPeak = max(array_map(static fn ($value) => is_numeric($value) ? (float) $value : 0.0, $telemetryTrend['sfp_temp_c'] ?? []) ?: [80]);
    $telemetryPoints = [];
    $telemetryCount = max(
        count($telemetryTrend['labels'] ?? []),
        count($telemetryTrend['ping_loss_percent'] ?? []),
        count($telemetryTrend['pon_module_temp_c'] ?? []),
        count($telemetryTrend['sfp_temp_c'] ?? []),
    );

    for ($i = 0; $i < $telemetryCount; $i++) {
        $pingValue = (float) ($telemetryTrend['ping_loss_percent'][$i] ?? 0);
        $ponValue = $telemetryTrend['pon_module_temp_c'][$i] ?? null;
        $sfpValue = $telemetryTrend['sfp_temp_c'][$i] ?? null;

        $telemetryPoints[] = [
            'label' => $telemetryTrend['labels'][$i] ?? '',
            'ping' => $pingValue,
            'pon' => $ponValue,
            'sfp' => $sfpValue,
            'ping_height' => max(10, (int) round(min(100, $pingValue))),
            'pon_height' => $ponValue !== null ? max(10, (int) round((((float) $ponValue) / max(1, $ponTrendPeak)) * 100)) : 8,
            'sfp_height' => $sfpValue !== null ? max(10, (int) round((((float) $sfpValue) / max(1, $sfpTrendPeak)) * 100)) : 8,
        ];
    }

    $topCards = [
        ['label' => 'PPPoE online', 'value' => $fmt($n['online_now'] ?? 0), 'hint' => 'Active subscriber sessions', 'tone' => 'cyan'],
        ['label' => 'User down', 'value' => $fmt($n['user_down'] ?? 0), 'hint' => 'Active subscribers currently offline', 'tone' => 'orange'],
        ['label' => 'WAN downlink', 'value' => $fmt($n['wan_download_mbps'] ?? 0, 2).' Mbps', 'hint' => 'Core ingress traffic', 'tone' => 'violet'],
        ['label' => 'WAN uplink', 'value' => $fmt($n['wan_upload_mbps'] ?? 0, 2).' Mbps', 'hint' => 'Core egress traffic', 'tone' => 'purple'],
        ['label' => 'User bandwidth', 'value' => $fmt($n['users_download_mbps'] ?? 0, 2).' / '.$fmt($n['users_upload_mbps'] ?? 0, 2), 'hint' => 'Users down/up Mbps', 'tone' => 'green'],
        ['label' => 'Link down', 'value' => $fmt($n['link_down'] ?? 0), 'hint' => 'OLT interfaces reported down', 'tone' => 'red'],
        ['label' => 'OLT impact', 'value' => $fmt($n['olt_offline'] ?? 0).' down · '.$fmt($n['olt_partial'] ?? 0).' partial', 'hint' => 'Offline and degraded chassis', 'tone' => 'amber'],
        ['label' => 'Fiber / alerts', 'value' => $fmt($n['fiber_alerts'] ?? 0), 'hint' => 'Fiber faults + open optical alerts', 'tone' => 'yellow'],
    ];

    $alertCards = [];
    if (($g['fiber_faults'] ?? 0) > 0) {
        $alertCards[] = ['title' => 'Fiber faults open', 'message' => $fmt($g['fiber_faults']).' unresolved fiber cut / field fault', 'tone' => 'red'];
    }
    if (($n['link_down'] ?? 0) > 0) {
        $alertCards[] = ['title' => 'Link down detected', 'message' => $fmt($n['link_down']).' OLT interface(s) are down', 'tone' => 'orange'];
    }
    if (($s['sla_breached'] ?? 0) > 0) {
        $alertCards[] = ['title' => 'SLA breach', 'message' => $fmt($s['sla_breached']).' ticket(s) crossed SLA deadline', 'tone' => 'rose'];
    }
    if (($g['critical_onus'] ?? 0) > 0) {
        $alertCards[] = ['title' => 'Critical ONU signal', 'message' => $fmt($g['critical_onus']).' ONU(s) below critical threshold', 'tone' => 'yellow'];
    }

    $wanInterfaces = collect($n['wan_interfaces'] ?? [])->take(6)->all();
    $downUsers = $n['down_users'] ?? [];
    $rootCauses = $n['root_causes'] ?? [];
    $zoneImpact = $n['zone_impact'] ?? [];
    $areaImpact = $n['area_impact'] ?? [];
    $activeOutages = $n['active_outages']['items'] ?? [];
    $activeOutageCount = $n['active_outages']['count'] ?? 0;
    $criticalOnuList = $n['critical_onu_list'] ?? [];
    $topImpact = $n['top_impact'] ?? [];
    $hotPonPorts = $n['hot_pon_ports'] ?? [];
    $oltReachability = $n['olt_reachability'] ?? [];
    $customerIndexUrl = \App\Filament\Resources\CustomerResource::getUrl('index');
    $ticketIndexUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
    $ticketCreateUrl = \App\Filament\Resources\SupportTicketResource::getUrl('create');
    $outageIndexUrl = \App\Filament\Resources\OutageResource::getUrl('index');
    $zoneIndexUrl = \App\Filament\Resources\ZoneResource::getUrl('index');
    $oltIndexUrl = \App\Filament\Resources\OltResource::getUrl('index');
    $opticalHubUrl = \App\Filament\Pages\OpticalMonitoringHub::getUrl();
    $filteredCustomerUrl = static function (array $filters) use ($customerIndexUrl): string {
        return $customerIndexUrl.'?'.http_build_query(['tableFilters' => $filters]);
    };
    $topImpactUrl = static function (array $item) use ($customerIndexUrl, $filteredCustomerUrl): string {
        return match ($item['type'] ?? '') {
            'zone' => ! empty($item['id']) ? $filteredCustomerUrl(['zone_id' => ['value' => $item['id']]]) : $customerIndexUrl,
            'area' => ! empty($item['id']) ? $filteredCustomerUrl(['area_id' => ['value' => $item['id']]]) : $customerIndexUrl,
            'olt' => ! empty($item['id']) ? \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $item['id']]) : \App\Filament\Resources\OltResource::getUrl('index'),
            'pon' => ! empty($item['olt_id']) ? \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $item['olt_id']]) : \App\Filament\Resources\OltResource::getUrl('index'),
            default => $customerIndexUrl,
        };
    };
    $incidentActions = [
        ['url' => $customerIndexUrl, 'eyebrow' => 'Subscribers', 'title' => 'Open impacted subscribers', 'meta' => 'Review down users, due status and service health'],
        ['url' => $ticketCreateUrl, 'eyebrow' => 'Ticket', 'title' => 'Create incident ticket', 'meta' => 'Open a support case for outage, link or auth issue'],
        ['url' => $outageIndexUrl, 'eyebrow' => 'Outage', 'title' => 'Open outage command desk', 'meta' => 'Track active incident and post field updates'],
        ['url' => $zoneIndexUrl, 'eyebrow' => 'Zone', 'title' => 'Review hotspot zones', 'meta' => 'See which area and zone are currently hit most'],
        ['url' => $oltIndexUrl, 'eyebrow' => 'OLT Core', 'title' => 'Start OLT recovery flow', 'meta' => 'Inspect chassis, links, load and degraded ports'],
        ['url' => $opticalHubUrl, 'eyebrow' => 'Optical', 'title' => 'Open optical monitoring', 'meta' => 'Trace critical ONU, temp and fiber health live'],
    ];

    $companyName = $this->companyName();
    $companyLogo = $this->companyLogoUrl();
    $companyInitial = $this->companyInitial();
    $fiberMapUrl = \App\Filament\Pages\FiberPlantMap::getUrl();
    $pollSeconds = (int) config('dashboard.noc_wall_poll_seconds', 60);
@endphp

<div class="isp-noc-wall" wire:poll.{{ $pollSeconds }}s="refreshWallData" id="isp-noc-wall">
    <header class="isp-noc-wall__header isp-noc-wall__header--hero" wire:key="noc-brand-{{ md5($companyName.'|'.($companyLogo ?? '')) }}">
        <div class="isp-noc-wall__brand">
            @if ($companyLogo)
                <img src="{{ $companyLogo }}" alt="" class="isp-noc-wall__brand-logo" loading="eager" />
            @else
                <span class="isp-noc-wall__brand-mark" aria-hidden="true">{{ $companyInitial }}</span>
            @endif
            <div class="isp-noc-wall__head-copy">
                <p class="isp-noc-wall__company-name">{{ $companyName }}</p>
                <p class="isp-noc-wall__eyebrow">Global Operations Center</p>
                <h1><span class="isp-noc-wall__title-accent">Live NOC Command Center</span></h1>
                <p class="isp-noc-wall__subtitle">Realtime bandwidth, subscriber impact, OLT health, ONU signal, outage heatmap and support pressure.</p>
            </div>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
            <div style="display:flex;align-items:center;gap:.5rem;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);padding:.55rem .85rem;border-radius:.9rem;color:#86efac;">
                <span style="display:inline-block;width:10px;height:10px;border-radius:999px;background:#22c55e;box-shadow:0 0 12px rgba(34,197,94,.85);animation:pulse 2s infinite;"></span>
                <span style="font-size:.85rem;font-weight:700;">Live refresh {{ $pollSeconds }}s</span>
            </div>
            <span id="noc-clock" style="font-family:monospace;font-size:1.4rem;font-weight:700;color:#fff;background:rgba(255,255,255,.05);padding:.55rem 1rem;border-radius:.9rem;">{{ now()->format('H:i:s') }}</span>
            <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" style="background:rgba(239,68,68,.18);color:#fca5a5;padding:.7rem 1rem;border-radius:.9rem;text-decoration:none;font-weight:700;border:1px solid rgba(239,68,68,.35);">Exit Wall</a>
        </div>
    </header>

    <section class="noc-hero-banner">
        <div>
            <div class="noc-hero-banner__eyebrow">NOC UI BUILD</div>
            <h2 class="noc-hero-banner__title">Nationwide Incident Console</h2>
            <p class="noc-hero-banner__copy">Live graph, ping loss, PON module temperature, SFP temperature, zone impact radar, outage drilldown and clickable recovery actions are active on this wall.</p>
        </div>
        <div class="noc-hero-banner__chips">
            <span class="noc-hero-banner__chip">Zone Heatmap</span>
            <span class="noc-hero-banner__chip">Area Drilldown</span>
            <span class="noc-hero-banner__chip">Critical ONU</span>
            <span class="noc-hero-banner__chip">Ping Loss</span>
            <span class="noc-hero-banner__chip">Ops Shortcuts</span>
        </div>
    </section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.85rem;margin-bottom:1rem;">
        @foreach ($incidentActions as $action)
            <a href="{{ $action['url'] }}" class="noc-action-card">
                <span class="noc-action-card__eyebrow">{{ $action['eyebrow'] }}</span>
                <span class="noc-action-card__title">{{ $action['title'] }}</span>
                <span class="noc-action-card__meta">{{ $action['meta'] }}</span>
            </a>
        @endforeach
    </div>

    @if ($alertCards !== [])
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;margin-bottom:1rem;">
            @foreach ($alertCards as $alertCard)
                <div class="noc-alert-card noc-alert-card--{{ $alertCard['tone'] }}">
                    <div class="noc-alert-card__title">{{ $alertCard['title'] }}</div>
                    <div style="margin-top:.4rem;color:#e2e8f0;font-size:.92rem;font-weight:600;">{{ $alertCard['message'] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <details class="gis-noc-map-embed" id="noc-map-mode">
        <summary style="cursor:pointer;padding:.75rem 1rem;background:#0f172a;color:#e2e8f0;font-weight:600;">
            🗺 NOC Map mode — live GIS fault overlay
            <a href="{{ $fiberMapUrl }}" style="float:right;color:#38bdf8;font-size:.85rem;">Full screen →</a>
        </summary>
        <iframe src="{{ $fiberMapUrl }}" title="Network operations map" loading="lazy"></iframe>
    </details>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1rem;">
        @foreach ($topCards as $card)
            <section class="noc-top-card noc-top-card--{{ $card['tone'] }}">
                <div class="noc-top-card__glow"></div>
                <div style="position:relative;">
                    <div style="display:flex;justify-content:space-between;gap:.5rem;align-items:flex-start;">
                        <span class="noc-top-card__label">{{ $card['label'] }}</span>
                        <span class="noc-top-card__dot"></span>
                    </div>
                    <div style="margin-top:.7rem;color:#fff;font-size:2rem;line-height:1;font-weight:900;">{{ $card['value'] }}</div>
                    <div style="margin-top:.45rem;color:#94a3b8;font-size:.82rem;">{{ $card['hint'] }}</div>
                </div>
            </section>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:1rem;margin-bottom:1rem;">
        <section style="background:rgba(15,23,42,.72);border:1px solid rgba(34,211,238,.22);border-radius:1rem;padding:1.1rem 1.2rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;color:#fff;font-size:1.05rem;">Access Telemetry</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Live ping-loss proxy, PON module temperature and SFP temperature graph.</p>
                </div>
                <div style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.22);padding:.5rem .75rem;border-radius:.8rem;color:#fca5a5;font-size:.78rem;font-weight:700;">
                    {{ $fmt($telemetryCurrent['ping_loss_devices'] ?? 0) }}/{{ $fmt($telemetryCurrent['olt_reachability_total'] ?? 0) }} OLT unreachable
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;margin-top:1rem;">
                <div class="noc-metric-mini noc-metric-mini--red">
                    <div class="noc-metric-mini__label">Ping Loss</div>
                    <div class="noc-metric-mini__value">{{ $fmt($telemetryCurrent['ping_loss_percent'] ?? 0, 1) }}%</div>
                    <div class="noc-metric-mini__meta">Mgmt reachability risk</div>
                </div>
                <div class="noc-metric-mini noc-metric-mini--cyan">
                    <div class="noc-metric-mini__label">PON Avg Temp</div>
                    <div class="noc-metric-mini__value">{{ ($telemetryCurrent['pon_module_avg_temp_c'] ?? null) !== null ? $fmt($telemetryCurrent['pon_module_avg_temp_c'], 1).'°C' : '—' }}</div>
                    <div class="noc-metric-mini__meta">Latest ONU optical sample</div>
                </div>
                <div class="noc-metric-mini noc-metric-mini--violet">
                    <div class="noc-metric-mini__label">SFP Avg Temp</div>
                    <div class="noc-metric-mini__value">{{ ($telemetryCurrent['sfp_avg_temp_c'] ?? null) !== null ? $fmt($telemetryCurrent['sfp_avg_temp_c'], 1).'°C' : '—' }}</div>
                    <div class="noc-metric-mini__meta">{{ ($telemetry['sfp_is_fallback'] ?? false) ? 'Uses chassis temp fallback' : 'Explicit transceiver telemetry' }}</div>
                </div>
                <div class="noc-metric-mini noc-metric-mini--amber">
                    <div class="noc-metric-mini__label">Peak Temp</div>
                    <div class="noc-metric-mini__value">
                        {{ ($telemetryCurrent['pon_module_max_temp_c'] ?? null) !== null ? $fmt($telemetryCurrent['pon_module_max_temp_c'], 1).'°C' : '—' }}
                        /
                        {{ ($telemetryCurrent['sfp_max_temp_c'] ?? null) !== null ? $fmt($telemetryCurrent['sfp_max_temp_c'], 1).'°C' : '—' }}
                    </div>
                    <div class="noc-metric-mini__meta">PON / SFP max</div>
                </div>
            </div>

            <div style="margin-top:1rem;height:215px;padding:.8rem .5rem .3rem;border-radius:1rem;background:rgba(0,0,0,.18);display:flex;align-items:flex-end;gap:8px;overflow:hidden;">
                @forelse ($telemetryPoints as $point)
                    <div class="noc-telemetry-point" title="{{ $point['label'] }} | Ping {{ $fmt($point['ping'], 1) }}% | PON {{ $point['pon'] !== null ? $fmt($point['pon'], 1).'°C' : '—' }} | SFP {{ $point['sfp'] !== null ? $fmt($point['sfp'], 1).'°C' : '—' }}">
                        <div class="noc-telemetry-point__bars">
                            <span class="noc-telemetry-bar noc-telemetry-bar--red" data-height="{{ $point['ping_height'] }}"></span>
                            <span class="noc-telemetry-bar noc-telemetry-bar--cyan" data-height="{{ $point['pon_height'] }}"></span>
                            <span class="noc-telemetry-bar noc-telemetry-bar--violet" data-height="{{ $point['sfp_height'] }}"></span>
                        </div>
                        <div class="noc-telemetry-point__label">{{ $point['label'] }}</div>
                    </div>
                @empty
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.95rem;background:rgba(0,0,0,.2);border-radius:1rem;">No telemetry history yet</div>
                @endforelse
            </div>

            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:.8rem;color:#94a3b8;font-size:.76rem;">
                <span style="display:inline-flex;align-items:center;gap:.35rem;"><span class="noc-legend-dot noc-legend-dot--red"></span> Ping loss %</span>
                <span style="display:inline-flex;align-items:center;gap:.35rem;"><span class="noc-legend-dot noc-legend-dot--cyan"></span> PON module temp</span>
                <span style="display:inline-flex;align-items:center;gap:.35rem;"><span class="noc-legend-dot noc-legend-dot--violet"></span> SFP temp</span>
            </div>
        </section>

        <section style="background:rgba(15,23,42,.72);border:1px solid rgba(129,140,248,.22);border-radius:1rem;padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:1rem;">
            <div>
                <h2 style="margin:0;color:#fff;font-size:1.05rem;">Top Impact Ranking</h2>
                <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Fastest path to the biggest subscriber impact right now.</p>
            </div>

            <div style="display:flex;flex-direction:column;gap:.6rem;">
                @forelse ($topImpact as $impact)
                    <a href="{{ $topImpactUrl($impact) }}" class="noc-impact-row">
                        <div style="min-width:0;">
                            <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;">
                                <span class="noc-impact-row__badge">{{ strtoupper($impact['type']) }}</span>
                                <span style="color:#fff;font-size:.9rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $impact['label'] }}</span>
                            </div>
                            <div style="margin-top:.18rem;color:#94a3b8;font-size:.76rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $impact['subtext'] }} · {{ $impact['detail'] }}</div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="color:#fff;font-size:1.1rem;font-weight:900;">{{ $fmt($impact['impact']) }}</div>
                            <div style="color:#64748b;font-size:.68rem;text-transform:uppercase;">Impact</div>
                        </div>
                    </a>
                @empty
                    <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No impact ranking available</div>
                @endforelse
            </div>

            <div style="background:rgba(0,0,0,.2);border-radius:1rem;padding:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.75rem;">
                    <h3 style="margin:0;color:#fff;font-size:.92rem;">Hot PON Ports</h3>
                    <span style="color:#94a3b8;font-size:.74rem;">Fault density + offline ONU</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:.55rem;">
                    @forelse ($hotPonPorts as $port)
                        <a href="{{ \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $port['olt_id']]) }}" class="noc-impact-row" style="padding:.72rem .8rem;">
                            <div style="min-width:0;">
                                <div style="color:#fff;font-size:.86rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $port['olt'] }} · {{ $port['port'] }}</div>
                                <div style="margin-top:.18rem;color:#94a3b8;font-size:.74rem;">Fault {{ $fmt($port['fault_percent'], 1) }}% · Critical {{ $fmt($port['critical']) }} / {{ $fmt($port['total']) }}</div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="color:#fca5a5;font-size:1rem;font-weight:900;">{{ $fmt($port['offline']) }}</div>
                                <div style="color:#64748b;font-size:.68rem;text-transform:uppercase;">Offline</div>
                            </div>
                        </a>
                    @empty
                        <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No hot PON data available</div>
                    @endforelse
                </div>
            </div>

            <div style="background:rgba(0,0,0,.2);border-radius:1rem;padding:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.75rem;">
                    <h3 style="margin:0;color:#fff;font-size:.92rem;">OLT Ping Board</h3>
                    <span style="color:#94a3b8;font-size:.74rem;">Live ICMP sample from app server</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:.55rem;">
                    @forelse ($oltReachability as $oltPing)
                        <a href="{{ \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $oltPing['id']]) }}" class="noc-impact-row" style="padding:.72rem .8rem;">
                            <div style="min-width:0;">
                                <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;">
                                    <span class="noc-impact-row__badge {{ $oltPing['reachable'] ? 'noc-impact-row__badge--up' : 'noc-impact-row__badge--down' }}">
                                        {{ $oltPing['reachable'] ? 'UP' : 'DOWN' }}
                                    </span>
                                    <span style="color:#fff;font-size:.86rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $oltPing['name'] }}</span>
                                </div>
                                <div style="margin-top:.18rem;color:#94a3b8;font-size:.74rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ $oltPing['host'] }} · Loss {{ $oltPing['packet_loss_percent'] !== null ? $fmt($oltPing['packet_loss_percent'], 1).'%' : '—' }}
                                    · RTT {{ $oltPing['avg_latency_ms'] !== null ? $fmt($oltPing['avg_latency_ms'], 2).' ms' : '—' }}
                                </div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="color:#fff;font-size:.96rem;font-weight:900;">{{ $oltPing['temperature_c'] !== null ? $fmt($oltPing['temperature_c'], 1).'°C' : '—' }}</div>
                                <div style="color:#64748b;font-size:.68rem;text-transform:uppercase;">{{ $oltPing['onus_offline'] }} onu off</div>
                            </div>
                        </a>
                    @empty
                        <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No OLT ping sample available</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,.95fr);gap:1rem;margin-bottom:1rem;">
        <section style="background:rgba(15,23,42,.7);border:1px solid rgba(139,92,246,.28);border-radius:1rem;padding:1.1rem 1.2rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;color:#fff;font-size:1.05rem;">Bandwidth Watch</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Last {{ count($trendBars) }} realtime points from subscriber sessions.</p>
                </div>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <div style="background:rgba(0,0,0,.26);padding:.6rem .8rem;border-radius:.8rem;min-width:120px;">
                        <div style="color:#c084fc;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;">Users Down</div>
                        <div style="color:#fff;font-size:1.3rem;font-weight:800;">{{ $fmt($n['users_download_mbps'] ?? 0, 2) }} Mbps</div>
                    </div>
                    <div style="background:rgba(0,0,0,.26);padding:.6rem .8rem;border-radius:.8rem;min-width:120px;">
                        <div style="color:#22c55e;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;">Users Up</div>
                        <div style="color:#fff;font-size:1.3rem;font-weight:800;">{{ $fmt($n['users_upload_mbps'] ?? 0, 2) }} Mbps</div>
                    </div>
                    <div style="background:rgba(0,0,0,.26);padding:.6rem .8rem;border-radius:.8rem;min-width:120px;">
                        <div style="color:#38bdf8;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;">WAN Down</div>
                        <div style="color:#fff;font-size:1.3rem;font-weight:800;">{{ $fmt($n['wan_download_mbps'] ?? 0, 2) }} Mbps</div>
                    </div>
                    <div style="background:rgba(0,0,0,.26);padding:.6rem .8rem;border-radius:.8rem;min-width:120px;">
                        <div style="color:#f472b6;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.1em;">WAN Up</div>
                        <div style="color:#fff;font-size:1.3rem;font-weight:800;">{{ $fmt($n['wan_upload_mbps'] ?? 0, 2) }} Mbps</div>
                    </div>
                </div>
            </div>

            <div style="height:180px;margin-top:1rem;padding-top:.5rem;display:flex;align-items:flex-end;gap:6px;">
                @forelse ($trendBars as $bar)
                    <div title="{{ $bar['label'] }} | D {{ $fmt($bar['download'], 2) }} Mbps | U {{ $fmt($bar['upload'], 2) }} Mbps" style="flex:1;min-width:0;">
                        <div class="noc-bandwidth-bar" data-height="{{ $bar['height'] }}"></div>
                    </div>
                @empty
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:.95rem;background:rgba(0,0,0,.2);border-radius:1rem;">No live bandwidth samples yet</div>
                @endforelse
            </div>
        </section>

        <section style="background:rgba(15,23,42,.7);border:1px solid rgba(34,211,238,.28);border-radius:1rem;padding:1.1rem 1.2rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div>
                    <h2 style="margin:0;color:#fff;font-size:1.05rem;">OLT / ONU Health</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Access network degradation summary.</p>
                </div>
                <div style="font-size:.8rem;font-weight:800;color:#a78bfa;">Avg health {{ $n['olt_avg_health'] ?? 'N/A' }}</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem;margin-top:1rem;">
                <div style="background:rgba(0,0,0,.24);padding:.9rem;border-radius:.9rem;border-left:3px solid #22c55e;">
                    <div style="color:#94a3b8;font-size:.78rem;text-transform:uppercase;">OLT Up</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.6rem;font-weight:800;">{{ $fmt($n['olts_online'] ?? 0) }}/{{ $fmt($n['olts_total'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.24);padding:.9rem;border-radius:.9rem;border-left:3px solid #ef4444;">
                    <div style="color:#94a3b8;font-size:.78rem;text-transform:uppercase;">OLT Offline</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.6rem;font-weight:800;">{{ $fmt($n['olt_offline'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.24);padding:.9rem;border-radius:.9rem;border-left:3px solid #22d3ee;">
                    <div style="color:#94a3b8;font-size:.78rem;text-transform:uppercase;">ONU Online</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.6rem;font-weight:800;">{{ $fmt($g['online_onus'] ?? 0) }}/{{ $fmt($g['total_onus'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.24);padding:.9rem;border-radius:.9rem;border-left:3px solid #f59e0b;">
                    <div style="color:#94a3b8;font-size:.78rem;text-transform:uppercase;">ONU Offline</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.6rem;font-weight:800;">{{ $fmt($g['offline_onus'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.24);padding:.9rem;border-radius:.9rem;border-left:3px solid #eab308;">
                    <div style="color:#94a3b8;font-size:.78rem;text-transform:uppercase;">Critical ONU</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.6rem;font-weight:800;">{{ $fmt($g['critical_onus'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.24);padding:.9rem;border-radius:.9rem;border-left:3px solid #8b5cf6;">
                    <div style="color:#94a3b8;font-size:.78rem;text-transform:uppercase;">Warning ONU</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.6rem;font-weight:800;">{{ $fmt($g['warning_onus'] ?? 0) }}</div>
                </div>
            </div>
        </section>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:1rem;">
        <section style="background:rgba(15,23,42,.64);border:1px solid rgba(255,255,255,.08);border-radius:1rem;padding:1.1rem 1.2rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div>
                    <h2 style="color:#fff;font-size:1.05rem;margin:0;">Access + Support Status</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Subscriber impact, routers and service desk pressure.</p>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.9rem;margin-top:1rem;">
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;border-left:3px solid #38bdf8;">
                    <div style="color:#94a3b8;font-size:.78rem;">MikroTik Core</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.55rem;font-weight:800;">{{ $fmt($n['mikrotik_online'] ?? 0) }}/{{ $fmt($n['mikrotik_total'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;border-left:3px solid #f97316;">
                    <div style="color:#94a3b8;font-size:.78rem;">User Down</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.55rem;font-weight:800;">{{ $fmt($n['user_down'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;border-left:3px solid #ef4444;">
                    <div style="color:#94a3b8;font-size:.78rem;">Open Tickets</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.55rem;font-weight:800;">{{ $fmt($s['open'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;border-left:3px solid #f43f5e;">
                    <div style="color:#94a3b8;font-size:.78rem;">SLA Breach</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.55rem;font-weight:800;">{{ $fmt($s['sla_breached'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;border-left:3px solid #a855f7;">
                    <div style="color:#94a3b8;font-size:.78rem;">Unassigned</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.55rem;font-weight:800;">{{ $fmt($s['unassigned'] ?? 0) }}</div>
                </div>
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;border-left:3px solid #eab308;">
                    <div style="color:#94a3b8;font-size:.78rem;">Critical Tickets</div>
                    <div style="margin-top:.35rem;color:#fff;font-size:1.55rem;font-weight:800;">{{ $fmt($s['critical'] ?? 0) }}</div>
                </div>
            </div>

            <div style="margin-top:1rem;background:rgba(0,0,0,.22);border-radius:1rem;padding:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.8rem;">
                    <h3 style="margin:0;color:#fff;font-size:.96rem;">WAN Interface Live</h3>
                    <span style="color:#94a3b8;font-size:.78rem;">Top active uplinks</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:.55rem;">
                    @forelse ($wanInterfaces as $iface)
                        <div style="display:grid;grid-template-columns:minmax(0,1.1fr) auto auto;gap:.8rem;align-items:center;background:rgba(15,23,42,.55);padding:.8rem .9rem;border-radius:.85rem;border:1px solid rgba(255,255,255,.05);">
                            <div style="min-width:0;">
                                <div style="color:#fff;font-weight:700;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $iface['server'] }} · {{ $iface['interface'] }}</div>
                            </div>
                            <div style="color:#38bdf8;font-size:.85rem;font-weight:700;">D {{ $fmt($iface['down_mbps'], 2) }} Mbps</div>
                            <div style="color:#f472b6;font-size:.85rem;font-weight:700;">U {{ $fmt($iface['up_mbps'], 2) }} Mbps</div>
                        </div>
                    @empty
                        <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No WAN interface samples available</div>
                    @endforelse
                </div>
            </div>

            <div style="margin-top:1rem;background:rgba(0,0,0,.22);border-radius:1rem;padding:1rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:.8rem;">
                    <h3 style="margin:0;color:#fff;font-size:.96rem;">Down Users With Reason</h3>
                    <span style="color:#94a3b8;font-size:.78rem;">Top impacted subscribers</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:.55rem;">
                    @forelse ($downUsers as $downUser)
                        <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $downUser['id']]) }}" style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,.9fr) auto;gap:.8rem;align-items:center;background:rgba(15,23,42,.55);padding:.85rem .9rem;border-radius:.85rem;border:1px solid rgba(255,255,255,.05);text-decoration:none;">
                            <div style="min-width:0;">
                                <div style="color:#fff;font-weight:700;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $downUser['name'] }} · {{ $downUser['code'] }}</div>
                                <div style="margin-top:.2rem;color:#94a3b8;font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $downUser['login'] }} · {{ $downUser['zone'] }} · {{ $downUser['server'] }}</div>
                            </div>
                            <div style="min-width:0;">
                                <div style="color:#fbbf24;font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $downUser['reason'] }}</div>
                                <div style="margin-top:.2rem;color:#94a3b8;font-size:.76rem;">Last seen {{ $downUser['last_seen'] }}</div>
                            </div>
                            <div style="color:#f87171;font-size:.74rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;">Open</div>
                        </a>
                    @empty
                        <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No down-user sample available</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section style="background:rgba(15,23,42,.64);border:1px solid rgba(255,255,255,.08);border-radius:1rem;padding:1.1rem 1.2rem;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div>
                    <h2 style="color:#fff;font-size:1.05rem;margin:0;">System Event Log</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Live operational exceptions and recovery notices.</p>
                </div>
            </div>
            <div class="isp-noc-wall__alerts" style="margin-top:1rem;display:flex;flex-direction:column;gap:.55rem;max-height:390px;overflow:auto;">
                @forelse ($d['alerts'] as $alert)
                    <div class="noc-system-alert noc-system-alert--{{ $alert['severity'] === 'danger' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info') }}">
                        <div style="display:flex;justify-content:space-between;gap:.8rem;align-items:center;">
                            <span style="font-weight:700;text-transform:uppercase;font-size:.72rem;color:#94a3b8;">{{ strtoupper($alert['type'] ?? 'event') }}</span>
                            <span style="font-size:.74rem;color:#64748b;">{{ \Illuminate\Support\Carbon::parse($alert['at'])->format('H:i:s') }}</span>
                        </div>
                        <div style="margin-top:.35rem;">{{ $alert['message'] }}</div>
                    </div>
                @empty
                    <div style="padding:1rem;border-radius:.8rem;background:rgba(16,185,129,.12);border-left:3px solid #10b981;color:#86efac;font-size:.88rem;text-align:center;font-weight:700;">
                        All systems nominal
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:1rem;margin-top:1rem;">
        <section style="background:rgba(15,23,42,.64);border:1px solid rgba(255,255,255,.08);border-radius:1rem;padding:1.1rem 1.2rem;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                <div>
                    <h2 style="color:#fff;font-size:1.05rem;margin:0;">Zone Impact Radar</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Which zones are taking the biggest hit right now.</p>
                </div>
                <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.24);padding:.55rem .8rem;border-radius:.8rem;color:#fca5a5;font-size:.8rem;font-weight:700;">
                    {{ $fmt($activeOutageCount) }} active outage{{ $activeOutageCount == 1 ? '' : 's' }}
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:.65rem;margin-top:1rem;">
                @forelse ($zoneImpact as $zone)
                    <a href="{{ $filteredCustomerUrl(['zone_id' => ['value' => $zone['zone_id'] ?: null]]) }}" class="noc-zone-row">
                        <div style="min-width:0;">
                            <div style="display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;">
                                <span style="color:#fff;font-size:.92rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $zone['zone'] }}</span>
                                <span style="color:#64748b;font-size:.72rem;white-space:nowrap;">{{ $zone['area_name'] }}</span>
                                @if ($zone['active_outage'])
                                    <span style="padding:.18rem .45rem;border-radius:999px;background:rgba(239,68,68,.14);color:#fca5a5;font-size:.67rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Outage</span>
                                @endif
                            </div>
                        </div>
                        <div style="text-align:center;">
                            <div style="color:#94a3b8;font-size:.68rem;text-transform:uppercase;">Down</div>
                            <div style="color:#fff;font-size:1.05rem;font-weight:800;">{{ $fmt($zone['down_users']) }}</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="color:#94a3b8;font-size:.68rem;text-transform:uppercase;">Expired</div>
                            <div style="color:#fbbf24;font-size:1.05rem;font-weight:800;">{{ $fmt($zone['expired']) }}</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="color:#94a3b8;font-size:.68rem;text-transform:uppercase;">Due</div>
                            <div style="color:#fb7185;font-size:1.05rem;font-weight:800;">{{ $fmt($zone['due']) }}</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="color:#94a3b8;font-size:.68rem;text-transform:uppercase;">Susp.</div>
                            <div style="color:#c084fc;font-size:1.05rem;font-weight:800;">{{ $fmt($zone['suspended']) }}</div>
                        </div>
                    </a>
                @empty
                    <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No zone impact data available</div>
                @endforelse
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.8rem;margin-top:1rem;">
                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;">
                    <h3 style="margin:0 0 .65rem;color:#fff;font-size:.92rem;">Area Heatmap</h3>
                    <div style="display:flex;flex-direction:column;gap:.55rem;">
                        @forelse ($areaImpact as $area)
                            <a href="{{ $filteredCustomerUrl(['area_id' => ['value' => $area['area_id'] ?: null]]) }}" style="display:flex;justify-content:space-between;gap:1rem;align-items:center;padding:.72rem .8rem;border-radius:.8rem;background:rgba(15,23,42,.55);border:1px solid rgba(255,255,255,.05);text-decoration:none;">
                                <div style="min-width:0;">
                                    <div style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                                        <span style="color:#fff;font-size:.84rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $area['area'] }}</span>
                                        @if ($area['active_outage'])
                                            <span style="padding:.15rem .4rem;border-radius:999px;background:rgba(239,68,68,.14);color:#fca5a5;font-size:.63rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;">Hot</span>
                                        @endif
                                    </div>
                                    <div style="margin-top:.18rem;color:#94a3b8;font-size:.73rem;">{{ $fmt($area['zones']) }} zone impact</div>
                                </div>
                                <div style="color:#fff;font-size:1rem;font-weight:800;">{{ $fmt($area['down_users']) }}</div>
                            </a>
                        @empty
                            <div style="color:#94a3b8;font-size:.82rem;">No area impact data</div>
                        @endforelse
                    </div>
                </div>

                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;">
                    <h3 style="margin:0 0 .65rem;color:#fff;font-size:.92rem;">Root Cause Breakdown</h3>
                    <div style="display:flex;flex-direction:column;gap:.5rem;">
                        @forelse ($rootCauses as $cause)
                            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;">
                                <span style="color:#cbd5e1;font-size:.82rem;">{{ $cause['reason'] }}</span>
                                <span style="color:#fff;font-weight:800;">{{ $fmt($cause['count']) }}</span>
                            </div>
                        @empty
                            <div style="color:#94a3b8;font-size:.82rem;">No cause data</div>
                        @endforelse
                    </div>
                </div>

                <div style="background:rgba(0,0,0,.22);padding:1rem;border-radius:.9rem;">
                    <h3 style="margin:0 0 .65rem;color:#fff;font-size:.92rem;">Active Outage Board</h3>
                    <div style="display:flex;flex-direction:column;gap:.55rem;">
                        @forelse ($activeOutages as $outage)
                            <a href="{{ $filteredCustomerUrl(['area_id' => ['value' => $outage['area_id'] ?: null]]) }}" style="padding:.7rem .75rem;border-radius:.8rem;background:rgba(15,23,42,.55);border-left:3px solid #ef4444;text-decoration:none;display:block;">
                                <div style="color:#fff;font-size:.84rem;font-weight:700;">{{ $outage['title'] }}</div>
                                <div style="margin-top:.2rem;color:#94a3b8;font-size:.76rem;">{{ $outage['area'] }} · {{ $outage['started'] }}</div>
                            </a>
                        @empty
                            <div style="color:#94a3b8;font-size:.82rem;">No active outage logged</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section style="background:rgba(15,23,42,.64);border:1px solid rgba(255,255,255,.08);border-radius:1rem;padding:1.1rem 1.2rem;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                <div>
                    <h2 style="color:#fff;font-size:1.05rem;margin:0;">Live ONU Details</h2>
                    <p style="margin:.3rem 0 0;color:#94a3b8;font-size:.84rem;">Critical optical units that need immediate attention.</p>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:1rem;">
                @forelse ($criticalOnuList as $onu)
                    <a href="{{ $onu['customer_id'] > 0 ? \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $onu['customer_id']]) : $oltIndexUrl }}" style="padding:.9rem;border-radius:.9rem;background:rgba(15,23,42,.55);border:1px solid rgba(255,255,255,.05);text-decoration:none;display:block;">
                        <div style="display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;">
                            <div style="min-width:0;">
                                <div style="color:#fff;font-size:.9rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $onu['customer'] }}</div>
                                <div style="margin-top:.18rem;color:#94a3b8;font-size:.76rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $onu['serial'] }} · {{ $onu['olt'] }}</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="color:#f87171;font-size:1rem;font-weight:900;">{{ $onu['rx_dbm'] !== null ? $fmt($onu['rx_dbm'], 2).' dBm' : '—' }}</div>
                                <div style="margin-top:.15rem;color:#94a3b8;font-size:.72rem;text-transform:uppercase;">{{ $onu['status'] }}</div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div style="padding:1rem;border-radius:.85rem;background:rgba(15,23,42,.55);color:#94a3b8;text-align:center;">No critical ONU detail available</div>
                @endforelse
            </div>
        </section>
    </div>
</div>

<style>
    .noc-hero-banner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
        padding: 1.15rem 1.25rem;
        border-radius: 1.15rem;
        border: 1px solid rgba(129, 140, 248, .28);
        background:
            radial-gradient(circle at top right, rgba(168, 85, 247, .18), transparent 34%),
            radial-gradient(circle at bottom left, rgba(34, 211, 238, .14), transparent 38%),
            linear-gradient(135deg, rgba(15, 23, 42, .92), rgba(30, 41, 59, .76));
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
    }

    .noc-hero-banner__eyebrow {
        color: #818cf8;
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .noc-hero-banner__title {
        margin: .3rem 0 0;
        color: #fff;
        font-size: 1.55rem;
        line-height: 1.1;
        font-weight: 900;
    }

    .noc-hero-banner__copy {
        margin: .45rem 0 0;
        color: #cbd5e1;
        font-size: .88rem;
        max-width: 780px;
    }

    .noc-hero-banner__chips {
        display: flex;
        flex-wrap: wrap;
        gap: .55rem;
        align-items: center;
    }

    .noc-hero-banner__chip {
        display: inline-flex;
        align-items: center;
        padding: .45rem .7rem;
        border-radius: 999px;
        color: #e2e8f0;
        font-size: .74rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        background: rgba(15, 23, 42, .55);
        border: 1px solid rgba(255, 255, 255, .08);
    }

    .noc-action-card {
        display: flex;
        flex-direction: column;
        gap: .35rem;
        padding: .95rem 1rem;
        border-radius: 1rem;
        text-decoration: none;
        background: linear-gradient(180deg, rgba(15, 23, 42, .84), rgba(15, 23, 42, .68));
        border: 1px solid rgba(99, 102, 241, .22);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .03);
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .noc-action-card:hover {
        transform: translateY(-1px);
        border-color: rgba(129, 140, 248, .45);
        box-shadow: 0 12px 30px rgba(15, 23, 42, .24);
    }

    .noc-action-card__eyebrow {
        color: #818cf8;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .noc-action-card__title {
        color: #fff;
        font-size: .95rem;
        font-weight: 700;
    }

    .noc-action-card__meta {
        color: #94a3b8;
        font-size: .78rem;
        line-height: 1.4;
    }

    .noc-zone-row {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) auto auto auto auto;
        gap: .7rem;
        align-items: center;
        background: rgba(15, 23, 42, .55);
        padding: .9rem;
        border-radius: .9rem;
        border: 1px solid rgba(255, 255, 255, .05);
        text-decoration: none;
        transition: transform .16s ease, border-color .16s ease;
    }

    .noc-zone-row:hover {
        transform: translateY(-1px);
        border-color: rgba(129, 140, 248, .35);
    }

    .noc-alert-card {
        background: rgba(15, 23, 42, .74);
        border-radius: 1rem;
        padding: 1rem 1.1rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .03);
        border: 1px solid rgba(255, 255, 255, .08);
        border-left-width: 4px;
    }

    .noc-alert-card__title {
        font-size: .74rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .noc-alert-card--red {
        border-color: rgba(239, 68, 68, .35);
        border-left-color: #ef4444;
    }

    .noc-alert-card--red .noc-alert-card__title { color: #ef4444; }
    .noc-alert-card--orange {
        border-color: rgba(249, 115, 22, .35);
        border-left-color: #f97316;
    }
    .noc-alert-card--orange .noc-alert-card__title { color: #f97316; }
    .noc-alert-card--rose {
        border-color: rgba(244, 63, 94, .35);
        border-left-color: #f43f5e;
    }
    .noc-alert-card--rose .noc-alert-card__title { color: #f43f5e; }
    .noc-alert-card--yellow {
        border-color: rgba(234, 179, 8, .35);
        border-left-color: #eab308;
    }
    .noc-alert-card--yellow .noc-alert-card__title { color: #eab308; }

    .noc-top-card {
        background: linear-gradient(180deg, rgba(15, 23, 42, .82), rgba(15, 23, 42, .64));
        border-radius: 1rem;
        padding: 1rem 1.1rem;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .08);
    }

    .noc-top-card__glow {
        position: absolute;
        right: -18px;
        top: -18px;
        width: 86px;
        height: 86px;
        border-radius: 999px;
        filter: blur(24px);
    }

    .noc-top-card__label {
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .noc-top-card__dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
    }

    .noc-top-card--cyan { border-color: rgba(34, 211, 238, .28); }
    .noc-top-card--cyan .noc-top-card__glow { background: rgba(34, 211, 238, .16); }
    .noc-top-card--cyan .noc-top-card__label { color: #22d3ee; }
    .noc-top-card--cyan .noc-top-card__dot { background: #22d3ee; box-shadow: 0 0 10px rgba(34, 211, 238, .8); }

    .noc-top-card--orange { border-color: rgba(249, 115, 22, .28); }
    .noc-top-card--orange .noc-top-card__glow { background: rgba(249, 115, 22, .16); }
    .noc-top-card--orange .noc-top-card__label { color: #f97316; }
    .noc-top-card--orange .noc-top-card__dot { background: #f97316; box-shadow: 0 0 10px rgba(249, 115, 22, .8); }

    .noc-top-card--violet { border-color: rgba(139, 92, 246, .28); }
    .noc-top-card--violet .noc-top-card__glow { background: rgba(139, 92, 246, .16); }
    .noc-top-card--violet .noc-top-card__label { color: #8b5cf6; }
    .noc-top-card--violet .noc-top-card__dot { background: #8b5cf6; box-shadow: 0 0 10px rgba(139, 92, 246, .8); }

    .noc-top-card--purple { border-color: rgba(192, 132, 252, .28); }
    .noc-top-card--purple .noc-top-card__glow { background: rgba(192, 132, 252, .16); }
    .noc-top-card--purple .noc-top-card__label { color: #c084fc; }
    .noc-top-card--purple .noc-top-card__dot { background: #c084fc; box-shadow: 0 0 10px rgba(192, 132, 252, .8); }

    .noc-top-card--green { border-color: rgba(16, 185, 129, .28); }
    .noc-top-card--green .noc-top-card__glow { background: rgba(16, 185, 129, .16); }
    .noc-top-card--green .noc-top-card__label { color: #10b981; }
    .noc-top-card--green .noc-top-card__dot { background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, .8); }

    .noc-top-card--red { border-color: rgba(239, 68, 68, .28); }
    .noc-top-card--red .noc-top-card__glow { background: rgba(239, 68, 68, .16); }
    .noc-top-card--red .noc-top-card__label { color: #ef4444; }
    .noc-top-card--red .noc-top-card__dot { background: #ef4444; box-shadow: 0 0 10px rgba(239, 68, 68, .8); }

    .noc-top-card--amber { border-color: rgba(245, 158, 11, .28); }
    .noc-top-card--amber .noc-top-card__glow { background: rgba(245, 158, 11, .16); }
    .noc-top-card--amber .noc-top-card__label { color: #f59e0b; }
    .noc-top-card--amber .noc-top-card__dot { background: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, .8); }

    .noc-top-card--yellow { border-color: rgba(234, 179, 8, .28); }
    .noc-top-card--yellow .noc-top-card__glow { background: rgba(234, 179, 8, .16); }
    .noc-top-card--yellow .noc-top-card__label { color: #eab308; }
    .noc-top-card--yellow .noc-top-card__dot { background: #eab308; box-shadow: 0 0 10px rgba(234, 179, 8, .8); }

    .noc-bandwidth-bar {
        min-height: 14px;
        background: linear-gradient(180deg, rgba(168, 85, 247, .98), rgba(59, 130, 246, .48));
        border-radius: 8px 8px 0 0;
        box-shadow: 0 0 12px rgba(99, 102, 241, .25);
    }

    .noc-metric-mini {
        background: rgba(0, 0, 0, .22);
        border-radius: .95rem;
        padding: .85rem .95rem;
        border: 1px solid rgba(255, 255, 255, .06);
    }

    .noc-metric-mini__label {
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }

    .noc-metric-mini__value {
        margin-top: .38rem;
        color: #fff;
        font-size: 1.3rem;
        font-weight: 900;
        line-height: 1;
    }

    .noc-metric-mini__meta {
        margin-top: .35rem;
        color: #94a3b8;
        font-size: .74rem;
    }

    .noc-metric-mini--red .noc-metric-mini__label { color: #f87171; }
    .noc-metric-mini--cyan .noc-metric-mini__label { color: #22d3ee; }
    .noc-metric-mini--violet .noc-metric-mini__label { color: #a78bfa; }
    .noc-metric-mini--amber .noc-metric-mini__label { color: #fbbf24; }

    .noc-telemetry-point {
        flex: 1;
        min-width: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        gap: .4rem;
    }

    .noc-telemetry-point__bars {
        flex: 1;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 3px;
    }

    .noc-telemetry-bar {
        width: 8px;
        min-height: 8px;
        border-radius: 999px 999px 0 0;
        opacity: .96;
    }

    .noc-telemetry-bar--red {
        background: linear-gradient(180deg, rgba(248,113,113,.98), rgba(239,68,68,.4));
        box-shadow: 0 0 10px rgba(239, 68, 68, .18);
    }

    .noc-telemetry-bar--cyan {
        background: linear-gradient(180deg, rgba(34,211,238,.98), rgba(14,165,233,.45));
        box-shadow: 0 0 10px rgba(34, 211, 238, .18);
    }

    .noc-telemetry-bar--violet {
        background: linear-gradient(180deg, rgba(168,85,247,.98), rgba(99,102,241,.45));
        box-shadow: 0 0 10px rgba(168, 85, 247, .18);
    }

    .noc-telemetry-point__label {
        color: #64748b;
        font-size: .63rem;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .noc-legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        display: inline-block;
    }

    .noc-legend-dot--red { background: #ef4444; }
    .noc-legend-dot--cyan { background: #22d3ee; }
    .noc-legend-dot--violet { background: #a855f7; }

    .noc-impact-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .85rem;
        padding: .85rem .9rem;
        border-radius: .9rem;
        background: rgba(15, 23, 42, .55);
        border: 1px solid rgba(255, 255, 255, .05);
        text-decoration: none;
        transition: transform .16s ease, border-color .16s ease;
    }

    .noc-impact-row:hover {
        transform: translateY(-1px);
        border-color: rgba(129, 140, 248, .35);
    }

    .noc-impact-row__badge {
        padding: .18rem .45rem;
        border-radius: 999px;
        background: rgba(129, 140, 248, .12);
        color: #a5b4fc;
        font-size: .63rem;
        font-weight: 800;
        letter-spacing: .08em;
    }

    .noc-impact-row__badge--up {
        background: rgba(34, 197, 94, .14);
        color: #86efac;
    }

    .noc-impact-row__badge--down {
        background: rgba(239, 68, 68, .14);
        color: #fca5a5;
    }

    .noc-system-alert {
        padding: .8rem .9rem;
        border-radius: .8rem;
        background: rgba(0, 0, 0, .26);
        color: #e2e8f0;
        font-size: .86rem;
        border-left: 3px solid #38bdf8;
    }

    .noc-system-alert--danger { border-left-color: #ef4444; }
    .noc-system-alert--warning { border-left-color: #f59e0b; }
    .noc-system-alert--info { border-left-color: #38bdf8; }

    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
        100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }

    @media (max-width: 1100px) {
        #isp-noc-wall > div[style*="grid-template-columns:minmax(0,1.35fr)"] ,
        #isp-noc-wall > div[style*="grid-template-columns:minmax(0,1.1fr)"] ,
        #isp-noc-wall > div[style*="grid-template-columns:minmax(0,1.15fr)"] {
            display: block !important;
        }

        #isp-noc-wall > div[style*="grid-template-columns:minmax(0,1.35fr)"] > section,
        #isp-noc-wall > div[style*="grid-template-columns:minmax(0,1.1fr)"] > section,
        #isp-noc-wall > div[style*="grid-template-columns:minmax(0,1.15fr)"] > section {
            margin-bottom: 1rem;
        }

        .noc-zone-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .noc-hero-banner__title {
            font-size: 1.2rem;
        }
    }
</style>

<script>
    const syncNocBars = () => {
        document.querySelectorAll('.noc-bandwidth-bar').forEach((bar) => {
            const height = Number(bar.dataset.height || 14);
            bar.style.height = `${height}%`;
        });

        document.querySelectorAll('.noc-telemetry-bar').forEach((bar) => {
            const height = Number(bar.dataset.height || 8);
            bar.style.height = `${height}%`;
        });
    };

    setInterval(() => {
        const el = document.getElementById('noc-clock');
        if (el) {
            el.textContent = new Date().toLocaleTimeString();
        }
    }, 1000);

    syncNocBars();

    let nocBarRaf = 0;
    const scheduleNocBars = () => {
        if (nocBarRaf) {
            cancelAnimationFrame(nocBarRaf);
        }
        nocBarRaf = requestAnimationFrame(() => {
            nocBarRaf = 0;
            syncNocBars();
        });
    };

    document.addEventListener('livewire:navigated', scheduleNocBars);
    document.addEventListener('livewire:morph.updated', scheduleNocBars);
</script>
