@props(['invoice', 'amount', 'size' => 'md'])

@php
    $compact = $size === 'sm';
    $btnClass = $compact
        ? 'portal-btn-primary portal-btn-pay !px-3 !py-1 text-xs'
        : 'portal-btn-primary portal-btn-pay';
@endphp

@if (($gateways['any'] ?? false) && $amount > 0)
    <form method="post" action="{{ route('portal.invoices.pay', $invoice) }}" class="inline-flex flex-wrap gap-2">
        @csrf
        @if ($bkashEnabled ?? false)
            <button type="submit" name="gateway" value="bkash" class="{{ $btnClass }}" style="{{ $compact ? '' : 'background:linear-gradient(135deg,#e2136e,#c4105c);' }}">bKash</button>
        @endif
        @if ($sslcommerzEnabled ?? false)
            <button type="submit" name="gateway" value="sslcommerz" class="{{ $btnClass }}" style="background:linear-gradient(135deg,#1e3a5f,#2563eb);">SSLCommerz</button>
        @endif
    @if ($nagadEnabled ?? false)
        <button type="submit" name="gateway" value="nagad" class="{{ $btnClass }}" style="background:linear-gradient(135deg,#f59e0b,#ea580c);">Nagad</button>
    @endif
    @if ($rocketEnabled ?? false)
        <button type="submit" name="gateway" value="rocket" class="{{ $btnClass }}" style="background:linear-gradient(135deg,#9333ea,#7c3aed);">Rocket</button>
    @endif
    @if ($piprapayEnabled ?? false)
        <button type="submit" name="gateway" value="piprapay" class="{{ $btnClass }}" style="background:linear-gradient(135deg,#0d9488,#059669);">PipraPay</button>
    @endif
</form>
@endif
