@props([
    'servers' => collect(),
    'variant' => 'landing',
    'title' => null,
    'subtitle' => null,
    'compact' => false,
])

@if ($servers->isNotEmpty())
    @php
        $heading = $title ?? ($variant === 'portal' ? 'Entertainment servers' : 'Movie & media servers');
        $lead = $subtitle ?? ($variant === 'portal'
            ? 'Quick links to FTP and streaming servers included with your package.'
            : 'Access our media servers and FTP libraries — links open in a new tab.');
    @endphp

    <section @class(['isp-movie-servers', 'isp-movie-servers--'.$variant, 'isp-movie-servers--compact' => $compact]) {{ $attributes }}>
        <div class="isp-movie-servers__head">
            <div>
                <p class="isp-movie-servers__eyebrow">Included with your connection</p>
                <h2 class="isp-movie-servers__title">{{ $heading }}</h2>
                @unless ($compact)
                    <p class="isp-movie-servers__lead">{{ $lead }}</p>
                @endunless
            </div>
            <span class="isp-movie-servers__count">{{ $servers->count() }} server{{ $servers->count() === 1 ? '' : 's' }}</span>
        </div>

        <div class="isp-movie-servers__grid">
            @foreach ($servers as $server)
                @php
                    $scheme = $server->linkScheme();
                    $schemeLabel = $scheme ? strtoupper($scheme) : 'LINK';
                @endphp
                <article class="isp-movie-server-card">
                    <a
                        href="{{ $server->resolvedUrl() }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="isp-movie-server-card__link"
                        title="Open {{ $server->name }}"
                    >
                        <span class="isp-movie-server-card__icon" aria-hidden="true">
                            @if (in_array($scheme, ['ftp', 'ftps', 'sftp'], true))
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                    <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
                                    <path d="M7 11l5-5 5 5"/>
                                    <path d="M12 6v10"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                    <rect x="2" y="4" width="20" height="14" rx="2"/>
                                    <path d="M10 9.5v5l4-2.5-4-2.5z"/>
                                </svg>
                            @endif
                        </span>
                        <span class="isp-movie-server-card__body">
                            <span class="isp-movie-server-card__name">
                                {{ $server->name }}
                                <span class="isp-movie-server-card__scheme">{{ $schemeLabel }}</span>
                            </span>
                            <span class="isp-movie-server-card__url">{{ $server->displayHost() ?: $server->displayUrl() }}</span>
                            @if (filled($server->note))
                                <span class="isp-movie-server-card__note">{{ $server->note }}</span>
                            @endif
                        </span>
                        <span class="isp-movie-server-card__cta">Open →</span>
                    </a>
                    <button
                        type="button"
                        class="isp-movie-server-card__copy"
                        data-copy-url="{{ $server->resolvedUrl() }}"
                        aria-label="Copy link for {{ $server->name }}"
                    >Copy</button>
                </article>
            @endforeach
        </div>
    </section>
@endif
