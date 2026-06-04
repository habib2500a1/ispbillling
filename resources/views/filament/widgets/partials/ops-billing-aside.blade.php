@php
    $aside = $aside ?? [];
    $stats = $aside['stats'] ?? [];
    $links = $aside['links'] ?? [];
@endphp

<aside class="isp-cmd-aside" aria-label="{{ $aside['title'] ?? 'Dashboard sidebar' }}">
    <header class="isp-cmd-aside__head">
        <h3>{{ $aside['title'] ?? 'Quick actions' }}</h3>
        <span class="isp-cmd-aside__hint">No chart — fast numbers & links</span>
    </header>

    @if ($stats !== [])
        <ul class="isp-cmd-aside__stats">
            @foreach ($stats as $row)
                <li class="isp-cmd-aside__stat">
                    @if (! empty($row['url']))
                        <a href="{{ $row['url'] }}" class="isp-cmd-aside__stat-link">
                            <span class="isp-cmd-aside__stat-label">{{ $row['label'] }}</span>
                            <strong class="isp-cmd-aside__stat-value">{{ $row['value'] }}</strong>
                            @if (! empty($row['hint']))
                                <span class="isp-cmd-aside__stat-hint">{{ $row['hint'] }}</span>
                            @endif
                        </a>
                    @else
                        <span class="isp-cmd-aside__stat-label">{{ $row['label'] }}</span>
                        <strong class="isp-cmd-aside__stat-value">{{ $row['value'] }}</strong>
                        @if (! empty($row['hint']))
                            <span class="isp-cmd-aside__stat-hint">{{ $row['hint'] }}</span>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($links !== [])
        <nav class="isp-cmd-aside__nav" aria-label="Quick links">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="isp-cmd-aside__link">
                    <x-filament::icon :icon="$link['icon']" class="isp-cmd-aside__link-icon" />
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>
    @endif
</aside>
