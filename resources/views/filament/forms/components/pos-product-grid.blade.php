@php
    $products = $products ?? [];
@endphp

<div class="iv-pos-grid-wrap">
    @if ($products === [])
        <p class="iv-pos-grid__empty">No in-stock products match — change warehouse or search.</p>
    @else
        <div class="iv-pos-grid" role="list">
            @foreach ($products as $product)
                <button
                    type="button"
                    class="iv-pos-grid__card"
                    wire:click="appendProductToSale({{ (int) $product['id'] }})"
                    role="listitem"
                    title="{{ $product['name'] }} — {{ number_format((float) $product['sell_price'], 0) }} BDT"
                >
                    @if (! empty($product['image_url']))
                        <img
                            src="{{ $product['image_url'] }}"
                            alt=""
                            class="iv-pos-grid__img"
                            loading="lazy"
                        >
                    @else
                        <span class="iv-pos-grid__img iv-pos-grid__img--ph" aria-hidden="true">{{ $product['initials'] }}</span>
                    @endif
                    <span class="iv-pos-grid__name">{{ $product['name'] }}</span>
                    <span class="iv-pos-grid__meta">
                        {{ number_format((float) $product['sell_price'], 0) }} BDT
                        · {{ (int) $product['warehouse_stock'] }} wh
                    </span>
                </button>
            @endforeach
        </div>
        @if (count($products) >= 48)
            <p class="iv-pos-grid__hint">Showing first 48 matches — refine search for more.</p>
        @endif
    @endif
</div>
