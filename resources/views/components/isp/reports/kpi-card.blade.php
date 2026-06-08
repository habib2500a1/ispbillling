@props([
    'label',
    'value',
    'hint' => null,
    'tone' => '',
    'href' => null,
    'delay' => 0,
])

@php
    $tag = $href ? 'a' : 'div';
    $classes = 'isp-bi-kpi' . ($tone ? " isp-bi-kpi--{$tone}" : '') . ($href ? ' isp-bi-kpi--link' : '');
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
    style="animation-delay: {{ (int) $delay }}ms"
>
    <p class="isp-bi-kpi__label">{{ $label }}</p>
    <p class="isp-bi-kpi__value">{{ $value }}</p>
    @if($hint)
        <p class="isp-bi-kpi__hint">{{ $hint }}</p>
    @endif
</{{ $tag }}>
