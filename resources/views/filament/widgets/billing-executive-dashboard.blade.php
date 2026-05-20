@php
    $kpis = $kpis ?? [];
    $growth = $growth ?? ['labels' => [], 'values' => [], 'max' => 1];
    $clients = $clients ?? [];
    $max = max(1, (float) ($growth['max'] ?? 1));
    $barColors = ['#8b5cf6', '#ec4899', '#14b8a6', '#64748b', '#3b82f6', '#f97316', '#06b6d4', '#a855f7', '#10b981'];
@endphp

<x-filament-widgets::widget>
    <section class="isp-billing-dash isp-billing-dash--pro">
        <header class="isp-billing-dash__head">
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

        <div class="isp-billing-dash__top">
            <article class="isp-billing-dash__chart-card">
                <h3 class="isp-billing-dash__card-title">Monthly bill growth</h3>
                <div class="isp-billing-dash__chart" role="img" aria-label="Monthly bill bar chart">
                    @foreach ($growth['labels'] as $i => $label)
                        @php
                            $value = (float) ($growth['values'][$i] ?? 0);
                            $height = $max > 0 ? max(8, ($value / $max) * 100) : 8;
                            $color = $barColors[$i % count($barColors)];
                        @endphp
                        <div class="isp-billing-dash__bar-col">
                            <div class="isp-billing-dash__bar-wrap">
                                <div
                                    class="isp-billing-dash__bar"
                                    style="height: {{ $height }}%; background: {{ $color }};"
                                >
                                    <span class="isp-billing-dash__bar-val">{{ number_format($value, 0) }}</span>
                                </div>
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
    </section>
</x-filament-widgets::widget>
