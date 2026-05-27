@extends('portal.layout')

@section('title', 'My bills')

@section('content')
    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">My bills</h1>
            <p class="portal-page-lead">View, download, and pay your invoices online with your preferred gateway.</p>
        </div>
        <span class="portal-live-badge">{{ $unpaidInvoices }} unpaid invoice{{ $unpaidInvoices === 1 ? '' : 's' }}</span>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card {{ $totalDue > 0 ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Outstanding balance</p>
            <p class="portal-summary-card__value">{{ number_format($totalDue, 2) }} BDT</p>
            <p class="portal-summary-card__meta">{{ $totalDue > 0 ? 'Pay any unpaid invoice to clear your balance.' : 'No unpaid balance right now.' }}</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Open invoices</p>
            <p class="portal-summary-card__value">{{ $unpaidInvoices }}</p>
            <p class="portal-summary-card__meta">{{ $nextDueDate ? 'Next due '.$nextDueDate->format('M j, Y') : 'No due date pending.' }}</p>
        </article>
        <article class="portal-summary-card portal-summary-card--warn">
            <p class="portal-summary-card__eyebrow">Payment gateways</p>
            <p class="portal-summary-card__value">{{ $gatewayCount }}</p>
            <div class="portal-inline-list" style="margin-top: 0.6rem;">
                @forelse ($paymentMethods as $method)
                    <span class="portal-inline-chip">{{ $method['label'] }}</span>
                @empty
                    <span class="portal-summary-card__meta">No online gateway active.</span>
                @endforelse
            </div>
        </article>
        <article class="portal-summary-card portal-summary-card--ok">
            <p class="portal-summary-card__eyebrow">Quick action</p>
            <p class="portal-summary-card__value">{{ $totalDue > 0 ? 'Pay now' : 'All clear' }}</p>
            <p class="portal-summary-card__meta">{{ $totalDue > 0 ? 'Open an invoice below and choose your gateway.' : 'Your account has no payable invoice at this moment.' }}</p>
        </article>
    </div>

    @if (($prepayEnabled ?? false) && ($gateways['any'] ?? false))
        <x-customer-prepay-form
            :quote="$prepayQuote"
            :action="route('portal.prepay.store')"
            :payment-methods="$paymentMethods"
            :max-months="$prepayMaxMonths"
            :quick-months="$prepayQuickMonths"
            variant="portal"
        />
    @endif

    <div class="portal-table-wrap">
        <table class="portal-billing-table">
            <thead class="bg-gradient-to-r from-violet-50 to-fuchsia-50 text-left text-xs font-bold uppercase text-violet-800">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3">Due date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $inv)
                    @php $due = round((float) $inv->total - (float) $inv->amount_paid, 2); @endphp
                    <tr class="hover:bg-violet-50/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.invoices.show', $inv) }}" class="portal-table-title font-mono">{{ $inv->invoice_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->period_start?->format('M j') ?? '—' }} - {{ $inv->period_end?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->due_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums {{ $due > 0 ? 'portal-amount-due' : 'portal-amount-ok' }}">{{ number_format($due, 2) }}</td>
                        <td class="px-4 py-3 capitalize">
                            <span class="portal-status-pill portal-status-pill--{{ strtolower($inv->status) }}">{{ $inv->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="portal-action-stack">
                                <a href="{{ route('portal.invoices.pdf', $inv) }}" class="portal-card-button">PDF</a>
                                @if ($due > 0 && ($gateways['any'] ?? false) && ! in_array($inv->status, ['void', 'cancelled'], true))
                                    @include('portal.partials.pay-buttons', ['invoice' => $inv, 'amount' => $due, 'size' => 'sm', 'paymentMethods' => $paymentMethods])
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">No bills found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
