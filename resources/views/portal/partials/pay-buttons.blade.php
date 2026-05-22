@props(['invoice', 'amount', 'size' => 'md', 'paymentMethods' => null])

@php
    $methods = $paymentMethods ?? \App\Support\PortalPaymentGateways::methodsForCustomerPortal();
    $compact = $size === 'sm';
@endphp

@if (count($methods) > 0 && $amount > 0)
    <form method="post" action="{{ route('portal.invoices.pay', $invoice) }}" class="portal-pay-form">
        @csrf
        @include('bill-payment.partials.payment-methods', ['methods' => $methods, 'compact' => $compact])
    </form>
@endif
