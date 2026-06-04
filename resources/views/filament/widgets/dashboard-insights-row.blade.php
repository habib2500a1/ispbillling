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
    $panelCount = (int) $show_revenue + (int) $show_online;
    $barAreaPx = 176;
@endphp

<x-filament-widgets::widget>
    <style>
        #isp-dash-insights-root {
            width: 100%;
            max-width: 100%;
        }
        #isp-dash-insights-root .isp-rev-chart,
        #isp-dash-insights-root .isp-onl-chart {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: flex-end !important;
            justify-content: space-between !important;
            gap: 0.2rem;
            width: 100% !important;
            min-height: 12.5rem;
            height: 12.5rem;
            padding: 0.35rem 0.1rem 0;
            box-sizing: border-box;
        }
        #isp-dash-insights-root .isp-rev-chart__col,
        #isp-dash-insights-root .isp-onl-chart__col {
            flex: 1 1 0 !important;
            min-width: 0 !important;
            max-width: none !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-end !important;
            height: 100% !important;
        }
        #isp-dash-insights-root .isp-rev-chart__stack,
        #isp-dash-insights-root .isp-onl-chart__stack {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-end !important;
            justify-content: center !important;
            gap: 2px;
            width: 100%;
            height: {{ $barAreaPx }}px;
        }
        #isp-dash-insights-root .isp-rev-chart__bar,
        #isp-dash-insights-root .isp-onl-chart__bar {
            display: block !important;
            flex: 1 1 0;
            max-width: 0.55rem;
            min-width: 3px;
            border-radius: 4px 4px 0 0;
            min-height: 3px;
        }
        #isp-dash-insights-root .isp-rev-chart__bar--col { background: #0d9488; }
        #isp-dash-insights-root .isp-rev-chart__bar--inv { background: #6366f1; opacity: 0.9; }
        #isp-dash-insights-root .isp-onl-chart__bar--on { background: #06b6d4; max-width: 0.45rem; }
        .dark #isp-dash-insights-root .isp-rev-chart__bar--col,
        [data-theme='dark'] #isp-dash-insights-root .isp-rev-chart__bar--col { background: #2dd4bf; }
        .dark #isp-dash-insights-root .isp-rev-chart__bar--inv,
        [data-theme='dark'] #isp-dash-insights-root .isp-rev-chart__bar--inv { background: #a78bfa; }
        #isp-dash-insights-root .isp-rev-chart__label,
        #isp-dash-insights-root .isp-onl-chart__label {
            display: block;
            margin-top: 0.2rem;
            font-size: 0.52rem;
            line-height: 1.1;
            color: var(--isp-text-muted, #94a3b8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
            text-align: center;
        }
        #isp-dash-insights-root .isp-onl-chart__label--empty {
            visibility: hidden;
        }
        .fi-dashboard-page .fi-wi-widget:has(#isp-dash-insights-root) > div {
            width: 100% !important;
            max-width: 100% !important;
            overflow: visible !important;
        }
    </style>

    <div id="isp-dash-insights-root" @class(['isp-dash-insights', 'isp-dash-insights--' . $panelCount . 'col'])>
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
    </div>
</x-filament-widgets::widget>
