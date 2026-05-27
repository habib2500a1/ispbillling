@props(['title', 'description' => null, 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'isp-hub-hero']) }}>
    @if ($eyebrow)
        <p class="isp-hub-hero__eyebrow">{{ $eyebrow }}</p>
    @endif
    <h2 class="isp-hub-hero__title">{{ $title }}</h2>
    @if($description)
        <p class="isp-hub-hero__desc">{{ $description }}</p>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="isp-hub-hero__body">
            {{ $slot }}
        </div>
    @endif
</div>
