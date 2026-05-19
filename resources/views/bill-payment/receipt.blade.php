@extends('bill-payment.layout', ['companyName' => $companyName])

@section('title', 'Payment receipt')

@section('content')
    <div class="bp-card">
        @php
            $isWallet = ($payment->payment_type ?? '') === \App\Support\PaymentType::WALLET_DEPOSIT;
        @endphp
        <h2 class="bp-title">Payment successful</h2>
        <p class="bp-sub">
            @if ($isWallet)
                Wallet top-up completed. Balance will apply to your next bill.
            @else
                Thank you — your payment has been recorded.
            @endif
        </p>

        @if (session('status'))
            <div class="bp-alert bp-alert-ok">{{ session('status') }}</div>
        @endif

        <div class="bp-summary-grid">
            <div class="bp-stat">
                <span class="text-xs uppercase text-slate-500">Amount</span>
                <strong>{{ number_format((float) $payment->amount, 2) }} BDT</strong>
            </div>
            <div class="bp-stat">
                <span class="text-xs uppercase text-slate-500">Method</span>
                <strong>{{ strtoupper($payment->method ?? '—') }}</strong>
            </div>
        </div>

        <ul class="mt-4 space-y-2 text-sm text-slate-700">
            <li><strong>Reference:</strong> {{ $payment->reference ?? $payment->gateway_transaction_id ?? '—' }}</li>
            <li><strong>Date:</strong> {{ $payment->paid_at?->format('d M Y H:i') }}</li>
            @if ($payment->invoice)
                <li><strong>Invoice:</strong> {{ $payment->invoice->invoice_number }}</li>
            @endif
            <li><strong>Client:</strong> {{ $payment->customer?->customer_code }}</li>
        </ul>

        <a href="{{ route('bill-payment.index') }}" class="bp-btn mt-6">Pay another bill</a>
        <a href="{{ route('portal.login') }}" class="mt-3 block text-center text-sm bp-link">Open customer portal</a>
    </div>
@endsection
