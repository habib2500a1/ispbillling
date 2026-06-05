@extends('portal.layout')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="portal-invoice-page">
        <div class="portal-invoice-hero {{ $balanceDue > 0 ? 'portal-invoice-hero--due' : 'portal-invoice-hero--paid' }}">
            <div class="portal-invoice-hero__info">
                <p class="portal-invoice-hero__eyebrow">Invoice</p>
                <h1 class="portal-invoice-hero__number">{{ $invoice->invoice_number }}</h1>
                <span class="portal-status-pill portal-status-pill--{{ strtolower($invoice->status) }}">{{ ucfirst($invoice->status) }}</span>
            </div>
            <div class="portal-invoice-hero__amount">
                <p class="portal-invoice-hero__amount-label">Amount due</p>
                <p class="portal-invoice-hero__amount-value">{{ number_format($balanceDue, 2) }} <span>BDT</span></p>
                <p class="portal-invoice-hero__amount-meta">Paid {{ number_format((float) $invoice->amount_paid, 2) }} of {{ number_format((float) $invoice->total, 2) }} BDT</p>
            </div>
        </div>

        <div class="portal-summary-grid portal-summary-grid--wide">
            <div class="portal-summary-card portal-summary-card--info">
                <p class="portal-summary-card__eyebrow">Issue date</p>
                <p class="portal-summary-card__value">{{ $invoice->issue_date?->format('M j, Y') ?? '—' }}</p>
                <p class="portal-summary-card__meta">Period {{ $invoice->period_start?->format('M j, Y') ?? '—' }} – {{ $invoice->period_end?->format('M j, Y') ?? '—' }}</p>
            </div>
            <div class="portal-summary-card portal-summary-card--warn">
                <p class="portal-summary-card__eyebrow">Due date</p>
                <p class="portal-summary-card__value">{{ $invoice->due_date?->format('M j, Y') ?? '—' }}</p>
                <p class="portal-summary-card__meta">{{ $balanceDue > 0 ? 'Payment required to clear this bill.' : 'This invoice is settled.' }}</p>
            </div>
            <div class="portal-summary-card">
                <p class="portal-summary-card__eyebrow">Invoice total</p>
                <p class="portal-summary-card__value">{{ number_format((float) $invoice->total, 2) }} BDT</p>
                <p class="portal-summary-card__meta">Line items below</p>
            </div>
        </div>

        @if ($canPay)
            <section id="pay" class="portal-invoice-pay-dock">
                <div class="portal-invoice-pay-dock__inner">
                    <div class="portal-invoice-pay-dock__copy">
                        <p class="portal-invoice-pay-dock__title">Pay online</p>
                        <p class="portal-invoice-pay-dock__meta">Choose your preferred gateway · {{ count($paymentMethods) }} method(s) available</p>
                    </div>
                    <div class="portal-invoice-pay-dock__methods">
                        @include('portal.partials.pay-buttons', ['invoice' => $invoice, 'amount' => $balanceDue, 'paymentMethods' => $paymentMethods])
                    </div>
                </div>
            </section>
        @endif

        <div class="portal-invoice-toolbar">
            <a href="{{ route('portal.invoices.pdf', $invoice) }}" class="portal-card-button portal-card-button--primary" target="_blank">
                Download PDF
            </a>
            <a href="{{ route('portal.bills.index') }}" class="portal-card-button">← All bills</a>
        </div>

        @if ($invoice->items->isNotEmpty())
            <section class="portal-invoice-lines">
                <h2 class="portal-surface-card__title">Line items</h2>
                <div class="portal-table-wrap">
                    <table class="portal-billing-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $line)
                                <tr>
                                    <td>{{ $line->description }}</td>
                                    <td class="text-right tabular-nums">{{ number_format((float) $line->quantity, 2) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format((float) $line->unit_price, 2) }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ number_format((float) $line->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
