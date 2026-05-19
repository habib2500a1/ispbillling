@props(['stats' => []])

@if (count($stats) > 0)
    <div {{ $attributes->merge(['class' => 'isp-hub-stat-grid']) }}>
        @foreach ($stats as $stat)
            <div class="isp-hub-stat {{ $stat['class'] ?? '' }}">
                <span class="isp-hub-stat-label">{{ $stat['label'] }}</span>
                <strong class="{{ $stat['valueClass'] ?? '' }}">{{ $stat['value'] }}</strong>
                @if (! empty($stat['hint']))
                    <span class="isp-hub-stat-hint">{{ $stat['hint'] }}</span>
                @endif
            </div>
        @endforeach
    </div>
@endif
