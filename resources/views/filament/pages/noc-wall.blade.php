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
    $customerIndexUrl = \App\Filament\Resources\CustomerResource::getUrl('index');
    $ticketIndexUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
    $outageIndexUrl = \App\Filament\Resources\OutageResource::getUrl('index');
    $zoneIndexUrl = \App\Filament\Resources\ZoneResource::getUrl('index');
    $oltIndexUrl = \App\Filament\Resources\OltResource::getUrl('index');
    $filteredCustomerUrl = static function (array $filters) use ($customerIndexUrl): string {
        return $customerIndexUrl.'?'.http_build_query(['tableFilters' => $filters]);
    };
@endphp

<div class="isp-noc-wall" wire:poll.15s id="isp-noc-wall">
    <header class="isp-noc-wall__header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:1rem;margin-bottom:1rem;">
        <div>
            <p style="color:#22d3ee;font-weight:700;letter-spacing:.14em;text-transform:uppercase;font-size:.72rem;margin:0 0 .35rem;">Global Operations Center</p>
            <h1 style="font-size:2rem;font-weight:800;color:#fff;line-height:1.1;margin:0;">{{ config('isp.company_name') }} · <span style="color:#a78bfa;">Live NOC Command Center</span></h1>
            <p style="margin:.45rem 0 0;color:#94a3b8;font-size:.9rem;">Realtime bandwidth, subscriber impact, OLT health, ONU signal, outage heatmap and support pressure.</p>
        </div>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
            <div style="display:flex;align-items:center;gap:.5rem;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);padding:.55rem .85rem;border-radius:.9rem;color:#86efac;">
                <span style="display:inline-block;width:10px;height:10px;border-radius:999px;background:#22c55e;box-shadow:0 0 12px rgba(34,197,94,.85);animation:pulse 2s infinite;"></span>
                <span style="font-size:.85rem;font-weight:700;">Live refresh 15s</span>
            </div>
            <span id="noc-clock" style="font-family:monospace;font-size:1.4rem;font-weight:700;color:#fff;background:rgba(255,255,255,.05);padding:.55rem 1rem;border-radius:.9rem;">{{ now()->format('H:i:s') }}</span>
            <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" style="background:rgba(239,68,68,.18);color:#fca5a5;padding:.7rem 1rem;border-radius:.9rem;text-decoration:none;font-weight:700;border:1px solid rgba(239,68,68,.35);">Exit Wall</a>
        </div>
    </header>

    <section class="noc-hero-banner">
        <div>
            <div class="noc-hero-banner__eyebrow">NOC UI BUILD</div>
            <h2 class="noc-hero-banner__title">Nationwide Incident Console</h2>
            <p class="noc-hero-banner__copy">Live graph, zone impact radar, area heatmap, outage drilldown, clickable down users and ONU details are active on this wall.</p>
        </div>
        <div class="noc-hero-banner__chips">
            <span class="noc-hero-banner__chip">Zone Heatmap</span>
            <span class="noc-hero-banner__chip">Area Drilldown</span>
            <span class="noc-hero-banner__chip">Critical ONU</span>
            <span class="noc-hero-banner__chip">Ops Shortcuts</span>
        </div>
    </section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.85rem;margin-bottom:1rem;">
        <a href="{{ $customerIndexUrl }}" class="noc-action-card">
            <span class="noc-action-card__eyebrow">Subscribers</span>
            <span class="noc-action-card__title">Open live subscriber list</span>
            <span class="noc-action-card__meta">Drill into down users and service status</span>
        </a>
        <a href="{{ $ticketIndexUrl }}" class="noc-action-card">
            <span class="noc-action-card__eyebrow">Support</span>
            <span class="noc-action-card__title">Open ticket command desk</span>
            <span class="noc-action-card__meta">Check SLA, assign and resolve incidents</span>
        </a>
        <a href="{{ $outageIndexUrl }}" class="noc-action-card">
            <span class="noc-action-card__eyebrow">Outages</span>
            <span class="noc-action-card__title">Manage outage log</span>
            <span class="noc-action-card__meta">Track area-level incidents and updates</span>
        </a>
        <a href="{{ $zoneIndexUrl }}" class="noc-action-card">
            <span class="noc-action-card__eyebrow">Zones</span>
            <span class="noc-action-card__title">Open zone management</span>
            <span class="noc-action-card__meta">Check impact by geography and branch</span>
        </a>
        <a href="{{ $oltIndexUrl }}" class="noc-action-card">
            <span class="noc-action-card__eyebrow">OLT Core</span>
            <span class="noc-action-card__title">Inspect OLT inventory</span>
            <span class="noc-action-card__meta">Review ports, load and optical health</span>
        </a>
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
    };

    setInterval(() => {
        const el = document.getElementById('noc-clock');
        if (el) {
            el.textContent = new Date().toLocaleTimeString();
        }
    }, 1000);

    syncNocBars();

    const nocWall = document.getElementById('isp-noc-wall');
    if (nocWall && typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(() => syncNocBars());
        observer.observe(nocWall, { childList: true, subtree: true });
    }
</script>
