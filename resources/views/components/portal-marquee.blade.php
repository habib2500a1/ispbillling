@props(['items', 'variant' => 'landing'])

@if ($items->isNotEmpty())
    @php
        $isLanding = $variant === 'landing';
    @endphp
    <div @class([
        'isp-portal-marquee',
        'isp-portal-marquee--landing' => $isLanding,
        'isp-portal-marquee--portal' => ! $isLanding,
    ])>
        <div class="isp-portal-marquee__track" aria-hidden="true">
            @foreach ($items as $item)
                <span class="isp-portal-marquee__item">
                    @if ($item->url)
                        <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">{{ $item->text }}</a>
                    @else
                        {{ $item->text }}
                    @endif
                </span>
                <span class="isp-portal-marquee__sep">•</span>
            @endforeach
            @foreach ($items as $item)
                <span class="isp-portal-marquee__item">
                    @if ($item->url)
                        <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer">{{ $item->text }}</a>
                    @else
                        {{ $item->text }}
                    @endif
                </span>
                <span class="isp-portal-marquee__sep">•</span>
            @endforeach
        </div>
    </div>
    @if ($isLanding)
        <style>
            .isp-portal-marquee--landing {
                margin: 0 0 1.25rem;
                overflow: hidden;
                border-radius: .5rem;
                border: 1px solid #1e293b;
                background: linear-gradient(90deg, #0f172a, #134e4a44);
            }
            .isp-portal-marquee--landing .isp-portal-marquee__track {
                display: flex;
                gap: 2rem;
                width: max-content;
                padding: .55rem 0;
                animation: isp-marquee-scroll 28s linear infinite;
                white-space: nowrap;
                font-size: .85rem;
                font-weight: 600;
                color: #5eead4;
            }
            .isp-portal-marquee--landing .isp-portal-marquee__item a { color: inherit; text-decoration: none; }
            .isp-portal-marquee--landing .isp-portal-marquee__item a:hover { text-decoration: underline; }
            .isp-portal-marquee--landing .isp-portal-marquee__sep { color: #475569; }
            @keyframes isp-marquee-scroll {
                from { transform: translateX(0); }
                to { transform: translateX(-50%); }
            }
        </style>
    @endif
@endif
