@extends('portal.layout')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="portal-page-head">
        <div>
            <p class="portal-summary-card__eyebrow">Bill / Invoice</p>
            <h1 class="portal-page-title" style="font-family: ui-monospace, monospace; text-transform: none;">{{ $invoice->invoice_number }}</h1>
            <p class="portal-page-lead">Status: <span class="portal-status-pill portal-status-pill--{{ strtolower($invoice->status) }}">{{ $invoice->status }}</span></p>
        </div>
        <div class="portal-action-stack">
            @if ($canPay)
                @include('portal.partials.pay-buttons', ['invoice' => $invoice, 'amount' => $balanceDue, 'paymentMethods' => $paymentMethods])
            @endif
            <a href="{{ route('portal.invoices.pdf', $invoice) }}" class="portal-card-button portal-card-button--primary">
                Download invoice PDF
            </a>
            <a href="{{ route('portal.bills.index') }}" class="portal-card-button">← All bills</a>
        </div>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <div class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Issue date</p>
            <p class="portal-summary-card__value">{{ $invoice->issue_date?->format('M j, Y') ?? '—' }}</p>
            <p class="portal-summary-card__meta">Billing period starts {{ $invoice->period_start?->format('M j, Y') ?? '—' }}</p>
        </div>
        <div class="portal-summary-card portal-summary-card--warn">
            <p class="portal-summary-card__eyebrow">Due date</p>
            <p class="portal-summary-card__value">{{ $invoice->due_date?->format('M j, Y') ?? '—' }}</p>
            <p class="portal-summary-card__meta">{{ $balanceDue > 0 ? 'Payment remains pending on this invoice.' : 'This invoice is already settled.' }}</p>
        </div>
        <div class="portal-summary-card">
            <p class="portal-summary-card__eyebrow">Invoice total</p>
            <p class="portal-summary-card__value">{{ number_format((float) $invoice->total, 2) }} BDT</p>
            <p class="portal-summary-card__meta">Paid so far {{ number_format((float) $invoice->amount_paid, 2) }} BDT</p>
        </div>
        <div class="portal-summary-card {{ $balanceDue > 0 ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Amount due</p>
            <p class="portal-summary-card__value">{{ number_format($balanceDue, 2) }} BDT</p>
            <p class="portal-summary-card__meta">{{ $canPay ? count($paymentMethods).' gateway(s) available now.' : 'No payment action required.' }}</p>
        </div>
    </div>

    @if ($invoice->items->isNotEmpty())
        <h2 class="mt-10 text-lg font-bold text-slate-800">Line items</h2>
        <div class="portal-table-wrap">
            <table class="portal-billing-table">
                <thead class="bg-violet-50 text-left text-xs font-bold uppercase text-violet-800">
                    <tr>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Unit</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($invoice->items as $line)
                        <tr>
                            <td class="px-4 py-3">{{ $line->description }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $line->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format((float) $line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
