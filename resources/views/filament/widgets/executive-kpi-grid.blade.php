@php
    $columns = $grid['columns'] ?? [];
    $updated = $grid['updated_at'] ?? null;
@endphp

<x-filament-widgets::widget>
    <section class="isp-kpi-wall" wire:poll.30s>
        <header class="isp-kpi-wall__head">
            <div>
                <h2 class="isp-kpi-wall__title">Live operations wall</h2>
                <p class="isp-kpi-wall__sub">Real-time KPIs for NOC, billing, support &amp; management</p>
            </div>
            @if ($updated)
                <span class="isp-kpi-wall__pulse" title="Auto-refresh every 30s">
                    <span class="isp-kpi-wall__dot" aria-hidden="true"></span>
                    Updated {{ \Carbon\Carbon::parse($updated)->diffForHumans() }}
                </span>
            @endif
        </header>

        <div class="isp-kpi-wall__grid">
            @foreach ($columns as $column)
                <div class="isp-kpi-wall__col isp-kpi-wall__col--{{ $column['tone'] ?? 'slate' }}">
                    <h3 class="isp-kpi-wall__col-title">{{ $column['title'] }}</h3>
                    <div class="isp-kpi-wall__cards">
                        @foreach ($column['cards'] as $card)
                            @if (! empty($card['url']))
                                <a href="{{ $card['url'] }}" class="isp-kpi-card">
                            @else
                                <div class="isp-kpi-card">
                            @endif
                                <span class="isp-kpi-card__label">{{ $card['label'] }}</span>
                                <span class="isp-kpi-card__value">{{ $card['value'] }}</span>
                                <span class="isp-kpi-card__hint">{{ $card['hint'] }}</span>
                            @if (! empty($card['url']))
                                </a>
                            @else
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
