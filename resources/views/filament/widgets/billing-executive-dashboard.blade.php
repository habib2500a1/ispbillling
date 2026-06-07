@php
    $kpis = $kpis ?? [];
    $source_notice = $source_notice ?? null;
    $growth = $growth ?? ['labels' => [], 'values' => [], 'max' => 1, 'months' => 6, 'range_label' => ''];
    $clients = $clients ?? [];
    $max = max(1, (float) ($growth['max'] ?? 1));
    $monthCount = (int) ($growth['months'] ?? count($growth['labels'] ?? []));
    $barColors = ['#64748b', '#6366f1', '#7c3aed', '#8b5cf6', '#06b6d4', '#10b981'];
    $formatBarValue = static function (float $value): string {
        if ($value >= 1000) {
            return number_format($value / 1000, $value >= 10000 ? 0 : 1).'k';
        }

        return number_format($value, 0);
    };
@endphp

<x-filament-widgets::widget>
    <section class="isp-billing-dash isp-billing-dash--pro" data-isp-dash-accordion>
        <button
            type="button"
            class="isp-dash-accordion__summary isp-dash-accordion__summary--billing"
            data-isp-dash-accordion-summary
            aria-expanded="true"
        >
            <span>
                <span class="isp-billing-dash__eyebrow">Billing overview</span>
                <span class="isp-billing-dash__title isp-dash-accordion__title">Monthly billing dashboard</span>
            </span>
            @if (! empty($updated_at))
                <span class="isp-billing-dash__updated">
                    {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
                </span>
            @endif
            <x-heroicon-m-chevron-down class="isp-dash-accordion__chevron h-5 w-5" />
        </button>

        <div class="isp-dash-accordion__body" data-isp-dash-accordion-body>
            <header class="isp-billing-dash__head isp-billing-dash__head--desktop">
                <div>
                    <p class="isp-billing-dash__eyebrow">Billing overview</p>
                    <h2 class="isp-billing-dash__title">Monthly billing dashboard</h2>
                </div>
                @if (! empty($updated_at))
                    <span class="isp-billing-dash__updated">
                        Updated {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
                    </span>
                @endif
            </header>

            @if (filled($source_notice))
                <div class="isp-billing-dash__notice" role="note">
                    <x-heroicon-o-information-circle class="isp-billing-dash__notice-icon" />
                    <p>{{ $source_notice }}</p>
                </div>
            @endif

            <div class="isp-billing-dash__top">
                <article class="isp-billing-dash__chart-card">
                    <div class="isp-billing-dash__chart-head">
                        <div>
                            <h3 class="isp-billing-dash__card-title">Monthly bill growth</h3>
                            @if (filled($growth['range_label'] ?? null))
                                <p class="isp-billing-dash__chart-range">{{ $growth['range_label'] }}</p>
                            @endif
                        </div>
                        <span class="isp-billing-dash__chart-badge">{{ $monthCount }} months</span>
                    </div>
                    <div
                        class="isp-billing-dash__chart isp-billing-dash__chart--six"
                        data-months="{{ $monthCount }}"
                        role="img"
                        aria-label="Monthly bill bar chart for the last {{ $monthCount }} months"
                    >
                        @foreach ($growth['labels'] as $i => $label)
                            @php
                                $value = (float) ($growth['values'][$i] ?? 0);
                                $height = $max > 0 ? max(8, ($value / $max) * 100) : 8;
                                $color = $barColors[$i % count($barColors)];
                                $isLatest = $i === count($growth['labels']) - 1;
                            @endphp
                            <div @class(['isp-billing-dash__bar-col', 'isp-billing-dash__bar-col--latest' => $isLatest])>
                                <span class="isp-billing-dash__bar-val" title="{{ number_format($value, 0) }} BDT">
                                    {{ $formatBarValue($value) }}
                                </span>
                                <div class="isp-billing-dash__bar-wrap">
                                    <div
                                        class="isp-billing-dash__bar"
                                        style="height: {{ $height }}%; background: {{ $color }};"
                                    ></div>
                                </div>
                                <span class="isp-billing-dash__bar-label">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="isp-billing-dash__table-card">
                    <div class="isp-billing-dash__table-head">
                        <h3 class="isp-billing-dash__card-title">Clients with due</h3>
                        <a href="{{ \App\Filament\Pages\DueReportPage::getUrl() }}" class="isp-billing-dash__link">
                            Full report
                        </a>
                    </div>
                    <div class="isp-billing-dash__table-wrap">
                        <table class="isp-billing-dash__table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Phone</th>
                                    <th class="text-right">Monthly</th>
                                    <th class="text-right">Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($clients as $row)
                                    <tr>
                                        <td>
                                            <a href="{{ $row['url'] }}" class="isp-billing-dash__user">{{ $row['login'] }}</a>
                                        </td>
                                        <td>{{ $row['phone'] }}</td>
                                        <td class="text-right">{{ number_format($row['monthly_bill'], 2) }}</td>
                                        <td class="text-right isp-billing-dash__due">{{ number_format($row['due'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="isp-billing-dash__empty">No due clients — great collection!</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>

            <div class="isp-billing-dash__kpis">
                @foreach ($kpis as $card)
                    <article class="isp-billing-dash__kpi isp-billing-dash__kpi--{{ $card['tone'] }}">
                        <div class="isp-billing-dash__kpi-icon">
                            <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                        </div>
                        <div class="isp-billing-dash__kpi-body">
                            <p class="isp-billing-dash__kpi-label">{{ $card['label'] }}</p>
                            <p class="isp-billing-dash__kpi-value">{{ number_format($card['value'], 0) }}</p>
                            <p class="isp-billing-dash__kpi-hint">{{ $card['hint'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
