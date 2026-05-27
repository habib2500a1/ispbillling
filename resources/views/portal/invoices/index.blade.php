@extends('portal.layout')

@section('title', 'Invoices')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Invoices</h1>
            <p class="portal-page-lead">Browse every generated invoice, outstanding balance, and payment status from one place.</p>
        </div>
        <span class="portal-live-badge">{{ $invoiceCount }} total invoice{{ $invoiceCount === 1 ? '' : 's' }}</span>
    </div>

    <div class="portal-summary-grid">
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Outstanding</p>
            <p class="portal-summary-card__value">{{ number_format($outstandingTotal, 2) }} BDT</p>
            <p class="portal-summary-card__meta">{{ $openInvoiceCount }} unpaid invoice{{ $openInvoiceCount === 1 ? '' : 's' }}</p>
        </article>
        <article class="portal-summary-card portal-summary-card--ok">
            <p class="portal-summary-card__eyebrow">Paid invoices</p>
            <p class="portal-summary-card__value">{{ $paidInvoiceCount }}</p>
            <p class="portal-summary-card__meta">Invoices fully cleared by payment.</p>
        </article>
    </div>

    <div class="portal-table-wrap">
        <table class="portal-billing-table">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3">Due date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Paid</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($invoices as $inv)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.invoices.show', $inv) }}" class="portal-table-title font-mono">{{ $inv->invoice_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->period_start?->format('M j') }} – {{ $inv->period_end?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->due_date?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $inv->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 capitalize text-slate-700">
                            <span class="portal-status-pill portal-status-pill--{{ strtolower($inv->status) }}">{{ $inv->status }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $invoices->links() }}
    </div>
@endsection
