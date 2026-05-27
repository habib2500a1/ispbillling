@extends('portal.layout')

@section('title', 'Payments')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Payments</h1>
            <p class="portal-page-lead">Track completed collections, receipts, and your latest successful payment.</p>
        </div>
        <span class="portal-live-badge">{{ $completedCount }} completed payment{{ $completedCount === 1 ? '' : 's' }}</span>
    </div>

    <div class="portal-summary-grid">
        <article class="portal-summary-card portal-summary-card--ok">
            <p class="portal-summary-card__eyebrow">Collected</p>
            <p class="portal-summary-card__value">{{ number_format($completedTotal, 2) }} BDT</p>
            <p class="portal-summary-card__meta">Successful payments applied to your account.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--warn">
            <p class="portal-summary-card__eyebrow">Refund total</p>
            <p class="portal-summary-card__value">{{ number_format($refundTotal, 2) }} BDT</p>
            <p class="portal-summary-card__meta">{{ $lastPaidAt ? 'Last payment '.$lastPaidAt->diffForHumans() : 'No completed payment yet.' }}</p>
        </article>
    </div>

    <div class="portal-table-wrap">
        <table class="portal-billing-table">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Receipt</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Receipt PDF</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $payment->receipt_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $payment->paid_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($payment->invoice)
                                <a href="{{ route('portal.invoices.show', $payment->invoice) }}" class="portal-table-title font-mono">{{ $payment->invoice->invoice_number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $payment->methodLabel() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $payment->typeLabel() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $payment->isRefund() ? 'portal-amount-due' : 'portal-amount-ok' }}">
                            {{ $payment->isRefund() ? '−' : '' }}{{ number_format((float) $payment->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 capitalize text-slate-700">
                            <span class="portal-status-pill portal-status-pill--{{ strtolower($payment->status) }}">{{ $payment->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($payment->status === 'completed')
                                <a
                                    href="{{ route('portal.payments.receipt', $payment) }}"
                                    class="portal-card-button portal-card-button--primary"
                                >
                                    Receipt
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">No payments recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $payments->links() }}
    </div>
@endsection
