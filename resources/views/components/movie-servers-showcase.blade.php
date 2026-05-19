@props([
    'servers' => collect(),
    'variant' => 'landing',
    'title' => null,
    'subtitle' => null,
])

@if ($servers->isNotEmpty())
    @php
        $heading = $title ?? ($variant === 'portal' ? 'Entertainment servers' : 'Movie & media servers');
        $lead = $subtitle ?? ($variant === 'portal'
            ? 'Quick links to FTP and streaming servers included with your package.'
            : 'Access our media servers and FTP libraries — links open in a new tab.');
    @endphp

    <section class="isp-movie-servers isp-movie-servers--{{ $variant }}" {{ $attributes }}>
        <div class="isp-movie-servers__head">
            <div>
                <p class="isp-movie-servers__eyebrow">Included with your connection</p>
                <h2 class="isp-movie-servers__title">{{ $heading }}</h2>
                <p class="isp-movie-servers__lead">{{ $lead }}</p>
            </div>
            <span class="isp-movie-servers__count">{{ $servers->count() }} server{{ $servers->count() === 1 ? '' : 's' }}</span>
        </div>

        <div class="isp-movie-servers__grid">
            @foreach ($servers as $server)
                <a
                    href="{{ $server->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="isp-movie-server-card"
                >
                    <span class="isp-movie-server-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <rect x="2" y="4" width="20" height="14" rx="2"/>
                            <path d="M10 9.5v5l4-2.5-4-2.5z"/>
                        </svg>
                    </span>
                    <span class="isp-movie-server-card__body">
                        <span class="isp-movie-server-card__name">{{ $server->name }}</span>
                        <span class="isp-movie-server-card__url">{{ parse_url($server->url, PHP_URL_HOST) ?: $server->url }}</span>
                        @if (filled($server->note))
                            <span class="isp-movie-server-card__note">{{ $server->note }}</span>
                        @endif
                    </span>
                    <span class="isp-movie-server-card__cta">Open →</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
