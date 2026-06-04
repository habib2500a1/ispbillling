@php
    /** @var \App\Models\Product $product */
    $sell = $product->effectiveSellPrice();
    $url = $product->imageUrl();
    $initials = mb_strtoupper(mb_substr((string) $product->name, 0, 2));
@endphp

<span class="iv-pos-option">
    @if ($url)
        <img src="{{ $url }}" alt="" class="iv-pos-option__img" loading="lazy">
    @else
        <span class="iv-pos-option__img iv-pos-option__img--ph">{{ $initials }}</span>
    @endif
    <span class="iv-pos-option__body">
        <strong class="iv-pos-option__name">{{ $product->name }}</strong>
        <span class="iv-pos-option__meta">
            @if ($product->barcode)
                {{ $product->barcode }} ·
            @endif
            wh {{ (int) ($warehouseStock ?? 0) }} · {{ number_format($sell, 0) }} BDT
        </span>
    </span>
</span>
