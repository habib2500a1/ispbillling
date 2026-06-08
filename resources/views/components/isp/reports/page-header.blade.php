@props([
    'eyebrow' => 'Reports',
    'title',
    'subtitle' => null,
    'scoreLabel' => null,
    'scoreValue' => null,
])

<section class="isp-bi-hero">
    <div>
        <p class="isp-bi-hero__eyebrow">{{ $eyebrow }}</p>
        <h1 class="isp-bi-hero__title">{{ $title }}</h1>
        @if($subtitle)
            <p class="isp-bi-hero__sub">{{ $subtitle }}</p>
        @endif
        @if(trim($slot))
            <div class="mt-3 flex flex-wrap gap-2">{{ $slot }}</div>
        @endif
    </div>
    @if($scoreLabel && $scoreValue !== null)
        <div class="isp-bi-hero__score">
            <span>{{ $scoreLabel }}</span>
            <strong>{{ $scoreValue }}</strong>
        </div>
    @endif
</section>
