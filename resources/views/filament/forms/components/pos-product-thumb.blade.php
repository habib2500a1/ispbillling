@php
    $productId = $productId ?? null;
    $product = filled($productId) ? \App\Models\Product::find($productId) : null;
    $url = $product?->imageUrl();
    $initials = $product
        ? mb_strtoupper(mb_substr((string) $product->name, 0, 2))
        : '?';
@endphp

<div class="iv-pos-thumb-wrap">
    @if ($url)
        <img src="{{ $url }}" alt="" class="iv-pos-thumb" loading="lazy">
    @else
        <span class="iv-pos-thumb iv-pos-thumb--placeholder" aria-hidden="true">{{ $initials }}</span>
    @endif
</div>
