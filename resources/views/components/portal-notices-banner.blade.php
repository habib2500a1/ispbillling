@props(['notices', 'variant' => 'landing'])

@if ($notices->isNotEmpty())
    @php $isLanding = $variant === 'landing'; @endphp
    <div @class([
        'isp-portal-notices',
        'isp-portal-notices--landing' => $isLanding,
        'isp-portal-notices--portal' => ! $isLanding,
    ])>
        @foreach ($notices as $notice)
            <article class="isp-portal-notices__card">
                <p class="isp-portal-notices__title">{{ $notice->title }}</p>
                @if ($notice->body)
                    <p class="isp-portal-notices__body">{{ $notice->body }}</p>
                @endif
            </article>
        @endforeach
    </div>
    @if ($isLanding)
        <style>
            .isp-portal-notices--landing { display: grid; gap: .75rem; margin-bottom: 1.25rem; }
            .isp-portal-notices--landing .isp-portal-notices__card {
                padding: .85rem 1rem;
                border-radius: .65rem;
                border: 1px solid #14532d;
                background: linear-gradient(135deg, #052e1644, #111b2e);
            }
            .isp-portal-notices--landing .isp-portal-notices__title { font-weight: 700; font-size: .9rem; color: #86efac; }
            .isp-portal-notices--landing .isp-portal-notices__body { margin-top: .35rem; font-size: .82rem; color: #94a3b8; }
        </style>
    @endif
@endif
