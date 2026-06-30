@php
    $revenue = $revenue ?? null;
    $online = $online ?? null;
    $labels = $revenue['labels'] ?? [];
    $collected = $revenue['collected'] ?? [];
    $invoiced = $revenue['invoiced'] ?? [];
    $onlineLabels = $online['labels'] ?? [];
    $onlineValues = $online['online'] ?? [];
    $revMax = max(1, max(array_merge($collected, $invoiced) ?: [0]));
    $onMax = max(1, max($onlineValues ?: [0]));
    $panelCount = (int) $show_revenue + (int) $show_online + (int) ($show_network ?? false) + (int) ($show_packages ?? false);
    $barAreaPx = 176;
    $network = $network ?? null;
    $packages = $packages ?? null;
    $bwLabels = $network['bandwidth_trend']['labels'] ?? [];
    $bwValues = $network['bandwidth_trend']['download_mbps'] ?? [];
    $pkgLabels = $packages['labels'] ?? [];
    $pkgValues = $packages['values'] ?? [];
    $growthLabels = $growth['labels'] ?? [];
    $growthValues = $growth['values'] ?? [];
@endphp

<x-filament-widgets::widget>
    <div class="isp-dash-analytics-wrap" data-isp-dash-accordion data-isp-lazy-reveal>
        <button
            type="button"
            class="isp-dash-accordion__summary isp-dash-accordion__summary--analytics"
            data-isp-dash-accordion-summary
            aria-expanded="true"
        >
            <span>
                <span class="isp-dash-analytics-wrap__eyebrow">Analytics</span>
                <span class="isp-dash-analytics-wrap__title">Revenue, network &amp; growth</span>
            </span>
            <x-heroicon-m-chevron-down class="isp-dash-accordion__chevron h-5 w-5" />
        </button>
        <div class="isp-dash-accordion__body" data-isp-dash-accordion-body>
            <div id="isp-dash-insights-root" class="isp-dash-insights isp-dash-insights--auto">
        @if ($show_revenue)
            <section class="isp-dash-insights__panel isp-dash-insights__panel--revenue">
                <header class="isp-dash-insights__head">
                    <div>
                        <h3 class="isp-dash-insights__title">Revenue trend</h3>
                        <p class="isp-dash-insights__sub">Collection vs invoice · last 14 days</p>
                    </div>
                    <div class="isp-dash-insights__kpis">
                        <div class="isp-dash-insights__kpi">
                            <span class="isp-dash-insights__kpi-label">Today</span>
                            <strong>{{ number_format($collected_today, 0) }} ৳</strong>
                        </div>
                        <div class="isp-dash-insights__kpi">
                            <span class="isp-dash-insights__kpi-label">14d collected</span>
                            <strong>{{ number_format($collected_sum, 0) }} ৳</strong>
                        </div>
                        <div class="isp-dash-insights__kpi isp-dash-insights__kpi--violet">
                            <span class="isp-dash-insights__kpi-label">14d invoiced</span>
                            <strong>{{ number_format($invoiced_sum, 0) }} ৳</strong>
                        </div>
                    </div>
                </header>

                <table
                    class="isp-rev-chart-table"
                    role="img"
                    aria-label="Revenue trend — collected vs invoiced, last 14 days"
                    style="display:table !important;width:100% !important;table-layout:fixed !important;border-collapse:collapse !important;height:12.5rem;"
                >
                    <tr style="display:table-row !important;">
                        @foreach ($labels as $i => $label)
                            @php
                                $col = (float) ($collected[$i] ?? 0);
                                $inv = (float) ($invoiced[$i] ?? 0);
                                $colPx = max(3, (int) round(($col / $revMax) * $barAreaPx));
                                $invPx = max(3, (int) round(($inv / $revMax) * $barAreaPx));
                            @endphp
                            <td style="display:table-cell !important;vertical-align:bottom;text-align:center;padding:0 2px;" title="{{ $label }}: {{ number_format($col, 0) }} / {{ number_format($inv, 0) }} ৳">
                                <div style="display:flex;align-items:flex-end;justify-content:center;gap:2px;height:{{ $barAreaPx }}px;width:100%;">
                                    <span style="display:block;width:0.5rem;min-height:3px;height:{{ $colPx }}px;background:#0d9488;border-radius:4px 4px 0 0;"></span>
                                    <span style="display:block;width:0.5rem;min-height:3px;height:{{ $invPx }}px;background:#6366f1;border-radius:4px 4px 0 0;"></span>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                    <tr style="display:table-row !important;">
                        @foreach ($labels as $label)
                            <td style="display:table-cell !important;font-size:0.52rem;line-height:1.1;color:#94a3b8;text-align:center;padding-top:0.25rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $label }}</td>
                        @endforeach
                    </tr>
                </table>

                <footer class="isp-dash-insights__legend">
                    <span><i class="isp-dash-insights__dot isp-dash-insights__dot--col"></i> Collected</span>
                    <span><i class="isp-dash-insights__dot isp-dash-insights__dot--inv"></i> Invoiced</span>
                </footer>
            </section>
        @endif

        @if ($show_online)
            <section class="isp-dash-insights__panel isp-dash-insights__panel--online">
                <header class="isp-dash-insights__head">
                    <div>
                        <h3 class="isp-dash-insights__title">Online subscribers</h3>
                        <p class="isp-dash-insights__sub">PPPoE sessions · last 24 hours</p>
                    </div>
                    <div class="isp-dash-insights__kpis">
                        <div class="isp-dash-insights__kpi isp-dash-insights__kpi--cyan">
                            <span class="isp-dash-insights__kpi-label">Live now</span>
                            <strong>{{ number_format($online_now) }}</strong>
                        </div>
                        <div class="isp-dash-insights__kpi">
                            <span class="isp-dash-insights__kpi-label">24h peak</span>
                            <strong>{{ number_format($online_peak) }}</strong>
                        </div>
                    </div>
                </header>

                <table
                    class="isp-rev-chart-table"
                    role="img"
                    aria-label="Online subscribers, last 24 hours"
                    style="display:table !important;width:100% !important;table-layout:fixed !important;border-collapse:collapse !important;height:12.5rem;"
                >
                    <tr style="display:table-row !important;">
                        @foreach ($onlineLabels as $i => $label)
                            @php
                                $val = (int) ($onlineValues[$i] ?? 0);
                                $hPx = max(3, (int) round(($val / $onMax) * $barAreaPx));
                            @endphp
                            <td style="display:table-cell !important;vertical-align:bottom;text-align:center;padding:0 2px;" title="{{ $label }}: {{ $val }}">
                                <div style="display:flex;align-items:flex-end;justify-content:center;height:{{ $barAreaPx }}px;width:100%;">
                                    <span style="display:block;width:0.45rem;min-height:3px;height:{{ $hPx }}px;background:#06b6d4;border-radius:4px 4px 0 0;"></span>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                    <tr style="display:table-row !important;">
                        @foreach ($onlineLabels as $i => $label)
                            <td style="display:table-cell !important;font-size:0.52rem;line-height:1.1;color:#94a3b8;text-align:center;padding-top:0.25rem;{{ $i % 3 !== 0 ? 'visibility:hidden;' : '' }}">{{ $label }}</td>
                        @endforeach
                    </tr>
                </table>

                <footer class="isp-dash-insights__legend">
                    <span><i class="isp-dash-insights__dot isp-dash-insights__dot--online"></i> Active sessions</span>
                </footer>
            </section>
        @endif

        @if ($show_network ?? false)
            <section class="isp-dash-insights__panel isp-dash-insights__panel--network">
                <header class="isp-dash-insights__head">
                    <div>
                        <h3 class="isp-dash-insights__title">Network health</h3>
                        <p class="isp-dash-insights__sub">Routers, ONUs &amp; live bandwidth</p>
                    </div>
                    <div class="isp-dash-insights__kpis">
                        <div class="isp-dash-insights__kpi isp-dash-insights__kpi--green">
                            <span class="isp-dash-insights__kpi-label">Health score</span>
                            <strong>{{ $network['health_score'] !== null ? $network['health_score'].'%' : '—' }}</strong>
                        </div>
                        <div class="isp-dash-insights__kpi">
                            <span class="isp-dash-insights__kpi-label">MikroTik</span>
                            <strong>{{ $network['mikrotik_online'] }}/{{ $network['mikrotik_total'] }}</strong>
                        </div>
                        <div class="isp-dash-insights__kpi">
                            <span class="isp-dash-insights__kpi-label">ONU</span>
                            <strong>{{ $network['onus_online'] }}/{{ $network['onus_total'] }}</strong>
                        </div>
                    </div>
                </header>

                <div class="isp-dash-insights__mini-stats">
                    <span>Live: <strong>{{ number_format($network['bandwidth_mbps'], 2) }} Mbps</strong></span>
                    @if (! empty($routers_url))
                        <a href="{{ $routers_url }}" class="isp-dash-insights__link">Routers →</a>
                    @endif
                    @if (! empty($bandwidth_url))
                        <a href="{{ $bandwidth_url }}" class="isp-dash-insights__link">Bandwidth →</a>
                    @endif
                </div>

                <div class="isp-dash-insights__sparkline" role="img" aria-label="Bandwidth trend">
                    @forelse ($bwValues as $i => $val)
                        @php $h = max(4, (int) round(((float) $val / $bandwidth_max) * 100)); @endphp
                        <div class="isp-dash-insights__spark-bar" style="height: {{ $h }}%;" title="{{ $bwLabels[$i] ?? '' }}: {{ number_format((float) $val, 2) }} Mbps"></div>
                    @empty
                        <p class="isp-dash-insights__empty">No bandwidth samples yet</p>
                    @endforelse
                </div>
            </section>
        @endif

        @if ($show_packages ?? false)
            <section class="isp-dash-insights__panel isp-dash-insights__panel--packages">
                <header class="isp-dash-insights__head">
                    <div>
                        <h3 class="isp-dash-insights__title">Package distribution</h3>
                        <p class="isp-dash-insights__sub">Active subscribers by line type</p>
                    </div>
                    <div class="isp-dash-insights__kpis">
                        <div class="isp-dash-insights__kpi isp-dash-insights__kpi--violet">
                            <span class="isp-dash-insights__kpi-label">Active total</span>
                            <strong>{{ number_format($packages['total'] ?? 0) }}</strong>
                        </div>
                    </div>
                </header>

                <div class="isp-dash-insights__pkg-chart" role="img" aria-label="Package distribution">
                    @foreach ($pkgLabels as $i => $label)
                        @php
                            $val = (int) ($pkgValues[$i] ?? 0);
                            $pct = ($packages['total'] ?? 0) > 0 ? round(($val / $packages['total']) * 100) : 0;
                        @endphp
                        <div class="isp-dash-insights__pkg-row">
                            <span class="isp-dash-insights__pkg-label">{{ $label }}</span>
                            <div class="isp-dash-insights__pkg-track">
                                <div class="isp-dash-insights__pkg-fill isp-dash-insights__pkg-fill--{{ $i }}" style="width: {{ max($pct, $val > 0 ? 4 : 0) }}%;"></div>
                            </div>
                            <span class="isp-dash-insights__pkg-val">{{ number_format($val) }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($show_growth ?? false)
            <section class="isp-dash-insights__panel isp-dash-insights__panel--growth">
                <header class="isp-dash-insights__head">
                    <div>
                        <h3 class="isp-dash-insights__title">Customer growth</h3>
                        <p class="isp-dash-insights__sub">New signups · last 14 days (joined date)</p>
                    </div>
                    <div class="isp-dash-insights__kpis">
                        <div class="isp-dash-insights__kpi isp-dash-insights__kpi--cyan">
                            <span class="isp-dash-insights__kpi-label">14d total</span>
                            <strong>{{ number_format($growth_total ?? 0) }}</strong>
                        </div>
                        <div class="isp-dash-insights__kpi">
                            <span class="isp-dash-insights__kpi-label">Peak day</span>
                            <strong>{{ number_format($growth_peak ?? 0) }}</strong>
                        </div>
                    </div>
                </header>

                <div class="isp-dash-insights__sparkline isp-dash-insights__sparkline--growth" role="img" aria-label="Customer growth trend">
                    @foreach ($growthValues as $i => $val)
                        @php $h = max(4, (int) round(((int) $val / ($growth_max ?? 1)) * 100)); @endphp
                        <div class="isp-dash-insights__spark-bar isp-dash-insights__spark-bar--growth" style="height: {{ $h }}%;" title="{{ $growthLabels[$i] ?? '' }}: {{ $val }}"></div>
                    @endforeach
                </div>
            </section>
        @endif
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
