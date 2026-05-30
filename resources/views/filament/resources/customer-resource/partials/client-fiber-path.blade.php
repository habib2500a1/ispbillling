@php
    use App\Filament\Pages\FiberPlantMap;
    use App\Services\Network\FiberPlantMapService;

    $fiberPath = app(FiberPlantMapService::class)->traceForCustomerId((int) $customer->getKey());
    $mapUrl = FiberPlantMap::canAccess()
        ? FiberPlantMap::getUrl().'?customer='.$customer->getKey()
        : null;
@endphp

<section class="isp-cv-card isp-cv-card--full fpm-sub-path">
    <div class="isp-cv-card__head">
        <h3 class="isp-cv-card__title">Fiber path / cable map</h3>
        @if ($mapUrl)
            <a href="{{ $mapUrl }}" class="isp-cv-link">Open on map →</a>
        @endif
    </div>

    @if (! $fiberPath['found'])
        <p class="isp-cv-muted text-sm">
            No fiber route mapped yet.
            @if ($mapUrl)
                <a href="{{ $mapUrl }}" class="text-teal-600 font-semibold hover:underline">Add on fiber map</a>
                — splitter theke customer porjonto cable, meter, ar color set korun.
            @endif
        </p>
    @else
        <p class="fpm-sub-path__total">
            Total fiber distance:
            <span class="text-teal-600">{{ number_format($fiberPath['total_length_m'], 0) }} m</span>
        </p>
        <ol class="fpm-sub-path__list">
            @foreach ($fiberPath['segments'] as $seg)
                <li class="fpm-sub-path__item">
                    <span class="fpm-sub-path__color" style="background: {{ $seg['cable_color_hex'] ?? '#2563eb' }}"></span>
                    <span class="fpm-sub-path__meta">
                        <strong>{{ $seg['from'] ?? '—' }} → {{ $seg['to'] ?? '—' }}</strong>
                        <span class="text-xs isp-cv-muted">
                            {{ $seg['cable_type'] ?? 'Cable' }}
                            @if (! empty($seg['direction']))
                                · {{ $seg['direction'] }}
                            @endif
                            @if (! empty($seg['cable_color']))
                                · {{ ucfirst($seg['cable_color']) }} fiber
                            @endif
                        </span>
                    </span>
                    <span class="fpm-sub-path__len">{{ number_format($seg['length_m'], 0) }}m</span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
