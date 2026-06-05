@extends('portal.layout')

@section('title', 'My bills')

@section('content')
    <div class="portal-bills-page">
        <div class="portal-bills-hero">
            <div class="portal-bills-hero__main">
                <p class="portal-bills-hero__eyebrow">Billing</p>
                <h1 class="portal-bills-hero__title">Pay your bill</h1>
                <p class="portal-bills-hero__lead">Secure online payment · PDF download · Payment history</p>
            </div>
            <div class="portal-bills-hero__due {{ $totalDue > 0 ? 'portal-bills-hero__due--pending' : 'portal-bills-hero__due--clear' }}">
                <p class="portal-bills-hero__due-label">Outstanding</p>
                <p class="portal-bills-hero__due-value">{{ number_format($totalDue, 2) }} <span>BDT</span></p>
                <p class="portal-bills-hero__due-meta">
                    @if ($totalDue > 0)
                        {{ $unpaidInvoices }} open invoice{{ $unpaidInvoices === 1 ? '' : 's' }}
                        @if ($nextDueDate) · Due {{ $nextDueDate->format('M j, Y') }}@endif
                    @else
                        All clear — no payment due
                    @endif
                </p>
            </div>
        </div>

        <div class="portal-summary-grid portal-summary-grid--wide portal-bills-kpis">
            <article class="portal-summary-card portal-summary-card--info">
                <p class="portal-summary-card__eyebrow">Open invoices</p>
                <p class="portal-summary-card__value">{{ $unpaidInvoices }}</p>
                <p class="portal-summary-card__meta">Bills waiting for payment</p>
            </article>
            <article class="portal-summary-card portal-summary-card--warn">
                <p class="portal-summary-card__eyebrow">Gateways</p>
                <p class="portal-summary-card__value">{{ $gatewayCount }}</p>
                <div class="portal-inline-list" style="margin-top: 0.5rem;">
                    @forelse ($paymentMethods as $method)
                        <span class="portal-inline-chip">{{ $method['label'] }}</span>
                    @empty
                        <span class="portal-summary-card__meta">Contact ISP to enable online pay</span>
                    @endforelse
                </div>
            </article>
            <article class="portal-summary-card portal-summary-card--ok">
                <p class="portal-summary-card__eyebrow">Payment history</p>
                <p class="portal-summary-card__value">View</p>
                <p class="portal-summary-card__meta">
                    <a href="{{ route('portal.payments.index') }}" class="portal-link">All receipts →</a>
                </p>
            </article>
        </div>

        @if ($prepayEnabled ?? false)
            @if ($prepayQuote ?? null)
                <div class="portal-bills-prepay">
                    <x-customer-prepay-form
                        :quote="$prepayQuote"
                        :action="route('portal.prepay.store')"
                        :payment-methods="$paymentMethods"
                        :max-months="$prepayMaxMonths"
                        :quick-months="$prepayQuickMonths"
                        variant="portal"
                    />
                </div>
            @else
                <div class="portal-panel portal-panel--info portal-bills-prepay-hint">
                    <p class="portal-panel__title">Pay multiple months in advance</p>
                    <p class="portal-summary-card__meta">Assign a monthly package rate to enable advance payment, or use <a href="{{ url('/pay?code='.urlencode((string) auth('customer')->user()->customer_code)) }}" class="portal-link">public bill pay</a>.</p>
                </div>
            @endif
        @endif

        <section class="portal-bills-list">
            <div class="portal-section-head">
                <div>
                    <h2 class="portal-surface-card__title">Your invoices</h2>
                    <p class="portal-surface-card__meta">Tap an invoice to view details and pay online</p>
                </div>
            </div>

            @forelse ($invoices as $inv)
                @php
                    $due = round((float) $inv->total - (float) $inv->amount_paid, 2);
                    $canPayRow = $due > 0 && ($gateways['any'] ?? false) && ! in_array($inv->status, ['void', 'cancelled'], true);
                    $statusClass = match ($inv->status) {
                        'paid' => 'portal-status-pill--success',
                        'open', 'partial' => 'portal-status-pill--warning',
                        'void', 'cancelled' => 'portal-status-pill--danger',
                        default => 'portal-status-pill--muted',
                    };
                @endphp
                <article class="portal-bill-card {{ $due > 0 ? 'portal-bill-card--due' : 'portal-bill-card--paid' }}">
                    <div class="portal-bill-card__head">
                        <div>
                            <a href="{{ route('portal.invoices.show', $inv) }}" class="portal-bill-card__number">{{ $inv->invoice_number }}</a>
                            <p class="portal-bill-card__period">
                                {{ $inv->period_start?->format('M j') ?? '—' }} – {{ $inv->period_end?->format('M j, Y') ?? '—' }}
                                · Due {{ $inv->due_date?->format('M j, Y') ?? '—' }}
                            </p>
                        </div>
                        <span class="portal-status-pill {{ $statusClass }}">{{ ucfirst($inv->status) }}</span>
                    </div>
                    <div class="portal-bill-card__amounts">
                        <div>
                            <span class="portal-bill-card__amt-label">Total</span>
                            <span class="portal-bill-card__amt-value">{{ number_format((float) $inv->total, 2) }} BDT</span>
                        </div>
                        <div>
                            <span class="portal-bill-card__amt-label">Balance</span>
                            <span class="portal-bill-card__amt-value {{ $due > 0 ? 'portal-bill-card__amt-value--due' : 'portal-bill-card__amt-value--ok' }}">
                                {{ number_format($due, 2) }} BDT
                            </span>
                        </div>
                    </div>
                    <div class="portal-bill-card__actions">
                        <a href="{{ route('portal.invoices.show', $inv) }}" class="portal-card-button">Details</a>
                        <a href="{{ route('portal.invoices.pdf', $inv) }}" class="portal-card-button" target="_blank">PDF</a>
                        @if ($canPayRow)
                            <a href="{{ route('portal.invoices.show', $inv) }}#pay" class="portal-card-button portal-card-button--primary">Pay {{ number_format($due, 0) }} BDT</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="portal-panel portal-bills-empty">
                    <p class="portal-empty-state">No bills found yet.</p>
                </div>
            @endforelse

            <div class="mt-4">{{ $invoices->links() }}</div>
        </section>

        {{-- Desktop table fallback --}}
        <div class="portal-bills-table-desktop portal-table-wrap">
            <table class="portal-billing-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Period</th>
                        <th>Due date</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Balance</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $inv)
                        @php $due = round((float) $inv->total - (float) $inv->amount_paid, 2); @endphp
                        <tr>
                            <td><a href="{{ route('portal.invoices.show', $inv) }}" class="portal-table-title font-mono">{{ $inv->invoice_number }}</a></td>
                            <td>{{ $inv->period_start?->format('M j') ?? '—' }} – {{ $inv->period_end?->format('M j, Y') ?? '—' }}</td>
                            <td>{{ $inv->due_date?->format('M j, Y') ?? '—' }}</td>
                            <td class="text-right tabular-nums">{{ number_format((float) $inv->total, 2) }}</td>
                            <td class="text-right tabular-nums {{ $due > 0 ? 'portal-amount-due' : 'portal-amount-ok' }}">{{ number_format($due, 2) }}</td>
                            <td><span class="portal-status-pill">{{ $inv->status }}</span></td>
                            <td class="text-right">
                                <div class="portal-action-stack">
                                    <a href="{{ route('portal.invoices.pdf', $inv) }}" class="portal-card-button" target="_blank">PDF</a>
                                    @if ($due > 0 && ($gateways['any'] ?? false) && ! in_array($inv->status, ['void', 'cancelled'], true))
                                        @include('portal.partials.pay-buttons', ['invoice' => $inv, 'amount' => $due, 'size' => 'sm', 'paymentMethods' => $paymentMethods])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
