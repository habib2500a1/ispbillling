@extends('reseller.layout')

@section('title', 'Due account')

@section('content')
@php
    $s = $summary;
    $c = $customerBreakdown;
    $m = $monthContext;
@endphp
@include('reseller.partials.page-header', [
    'title' => 'Due account',
    'subtitle' => 'Payable now vs lifetime totals — HQ wholesale is ~330 BDT/subscriber per bill.',
])

<div class="rsl-due-callout">
    <p><strong>Why do lifetime totals look large?</strong> The <em>lifetime</em> lines below sum all past months' bills and payments.
        Your <strong>payable now</strong> balance is shown in the hero boxes above (HQ ≈ {{ number_format($s['admin_due'], 0) }} BDT · Customer ≈ {{ number_format($c['due'], 0) }} BDT).</p>
    <p class="rsl-due-callout__month">{{ $m['label'] }}: {{ $m['subscriber_bills'] }} subscriber bill · wholesale {{ number_format($m['hq_wholesale'], 0) }} BDT · retail billed {{ number_format($m['customer_invoiced'], 0) }} BDT</p>
</div>

<div class="rsl-due-grid">
    <div class="rsl-panel rsl-panel-pad rsl-due-panel rsl-due-panel--hq">
        <h2 class="rsl-due-panel__title">① Admin (HQ) — you owe now</h2>
        <p class="rsl-due-panel__sub">Wholesale ~330 BDT per subscriber bill. Settlements and collections reduce this.</p>

        <div class="rsl-due-hero rsl-due-hero--hq">
            <p class="rsl-due-hero__label">Current HQ due (payable)</p>
            <p class="rsl-due-hero__value">{{ number_format($s['admin_due'], 2) }} BDT</p>
        </div>

        <dl class="rsl-due-breakdown">
            <div class="rsl-due-breakdown__row">
                <dt>+ Wholesale (lifetime, all months)</dt>
                <dd class="rsl-due-breakdown__debit">+{{ number_format($s['total_wholesale_accrued'] ?? 0, 2) }}</dd>
            </div>
            @if (($s['debit_notes'] ?? 0) > 0)
                <div class="rsl-due-breakdown__row">
                    <dt>+ Debit notes</dt>
                    <dd class="rsl-due-breakdown__debit">+{{ number_format($s['debit_notes'], 2) }}</dd>
                </div>
            @endif
            <div class="rsl-due-breakdown__row rsl-due-breakdown__row--total">
                <dt>Total increases (lifetime)</dt>
                <dd>{{ number_format($s['total_debits'] ?? 0, 2) }} BDT</dd>
            </div>
            <div class="rsl-due-breakdown__row">
                <dt>− Paid to HQ (settlement)</dt>
                <dd class="rsl-due-breakdown__credit">−{{ number_format($s['paid_to_hq_settlement'] ?? 0, 2) }}</dd>
            </div>
            <div class="rsl-due-breakdown__row">
                <dt>− Customer payment (HQ share, lifetime)</dt>
                <dd class="rsl-due-breakdown__credit">−{{ number_format($s['reduced_from_customer_payments'] ?? 0, 2) }}</dd>
            </div>
            @if (($s['credit_notes'] ?? 0) > 0)
                <div class="rsl-due-breakdown__row">
                    <dt>− Credit notes</dt>
                    <dd class="rsl-due-breakdown__credit">−{{ number_format($s['credit_notes'], 2) }}</dd>
                </div>
            @endif
            <div class="rsl-due-breakdown__row rsl-due-breakdown__row--final">
                <dt>= Balance due (calc)</dt>
                <dd class="rsl-due-breakdown__debit">{{ number_format($s['calculated_due'] ?? $s['admin_due'], 2) }} BDT</dd>
            </div>
        </dl>
        <p class="rsl-due-footnote">Your margin (lifetime): {{ number_format($s['margin_total'], 2) }} BDT</p>
    </div>

    <div class="rsl-panel rsl-panel-pad rsl-due-panel rsl-due-panel--customer">
        <h2 class="rsl-due-panel__title">② Customers owe you now</h2>
        <p class="rsl-due-panel__sub">Retail bills minus what you already collected.</p>

        <div class="rsl-due-hero rsl-due-hero--customer">
            <p class="rsl-due-hero__label">Current customer due</p>
            <p class="rsl-due-hero__value">{{ number_format($c['due'], 2) }} BDT</p>
        </div>

        <dl class="rsl-due-breakdown">
            <div class="rsl-due-breakdown__row">
                <dt>Total invoiced (lifetime)</dt>
                <dd>{{ number_format($c['invoiced'], 2) }} BDT</dd>
            </div>
            <div class="rsl-due-breakdown__row">
                <dt>− Collected (lifetime)</dt>
                <dd class="rsl-due-breakdown__credit">−{{ number_format($c['collected'], 2) }}</dd>
            </div>
            @if ($c['discounted'] > 0)
                <div class="rsl-due-breakdown__row">
                    <dt>− Discount / waive</dt>
                    <dd class="rsl-due-breakdown__credit">({{ number_format($c['discounted'], 2) }})</dd>
                </div>
            @endif
            <div class="rsl-due-breakdown__row rsl-due-breakdown__row--final">
                <dt>= Due now</dt>
                <dd class="rsl-due-breakdown__credit">{{ number_format($c['due'], 2) }} BDT</dd>
            </div>
        </dl>
        <p class="rsl-due-footnote">
            <a href="{{ route('reseller.customers.index', ['due' => 1]) }}" class="rsl-link">Due subscribers list →</a>
        </p>
    </div>
</div>

<div class="rsl-due-grid rsl-due-grid--2">
    <div class="rsl-panel rsl-panel-pad">
        <h2 class="rsl-due-panel__title rsl-due-panel__title--sm">What reduces due?</h2>
        <ul class="rsl-due-list">
            <li><strong>HQ due:</strong> Settlement to admin · Customer payment (HQ share) · Credit note</li>
            <li><strong>Customer due:</strong> You collect payment · Invoice discount</li>
            <li><strong>HQ increases:</strong> New monthly bill (wholesale accrual per subscriber)</li>
        </ul>
    </div>
    <div class="rsl-panel rsl-panel-pad">
        <h2 class="rsl-due-panel__title rsl-due-panel__title--sm">Billing policy</h2>
        <dl class="rsl-due-breakdown">
            <div class="rsl-due-breakdown__row"><dt>Settlement</dt><dd>{{ $settlementMode }}</dd></div>
            <div class="rsl-due-breakdown__row"><dt>Credit limit</dt><dd>{{ number_format($s['credit_limit'], 0) }} BDT ({{ $s['utilization_percent'] }}%)</dd></div>
            <div class="rsl-due-breakdown__row"><dt>Customer policy</dt><dd>{{ $customerPolicy }}</dd></div>
        </dl>
    </div>
</div>

<div class="rsl-panel rsl-panel-pad mb-6">
    <h2 class="rsl-due-panel__title rsl-due-panel__title--sm">Customer due aging</h2>
    <div class="rsl-quota-grid" style="margin-top:0.75rem">
        <div class="rsl-quota-item"><span class="rsl-text-muted">0–30d</span><strong>{{ number_format($aging['bucket_30'], 0) }}</strong></div>
        <div class="rsl-quota-item"><span class="rsl-text-muted">31–60d</span><strong>{{ number_format($aging['bucket_60'], 0) }}</strong></div>
        <div class="rsl-quota-item"><span class="rsl-text-muted">61–90d</span><strong>{{ number_format($aging['bucket_90'], 0) }}</strong></div>
        <div class="rsl-quota-item"><span class="rsl-text-muted">90+d</span><strong>{{ number_format($aging['bucket_90_plus'], 0) }}</strong></div>
    </div>
</div>

<div class="rsl-panel overflow-hidden mb-6">
    <div class="rsl-panel-pad" style="border-bottom:1px solid var(--rsl-border)">
        <h2 class="rsl-due-panel__title rsl-due-panel__title--sm">Subscriber lines — {{ $lineTotals['count'] }} ({{ $m['label'] }} bill column)</h2>
        <p class="rsl-due-panel__sub" style="margin:0.35rem 0 0">
            {{ $lineTotals['with_bill'] }} billed this month · Customer due {{ number_format($lineTotals['retail_due'], 2) }} BDT
            · HQ wholesale this month {{ number_format($lineTotals['wholesale'], 2) }} BDT
        </p>
    </div>
    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
        <table class="rsl-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Bill</th>
                    <th class="text-right">Retail</th>
                    <th class="text-right">Customer due</th>
                    <th class="text-right">HQ (~330)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subscriberLines as $i => $line)
                    <tr class="{{ ! $line['has_accrual'] && $line['status'] !== 'active' ? 'rsl-table-row--muted' : '' }}">
                        <td>{{ $i + 1 }}</td>
                        <td class="font-mono text-xs">{{ $line['customer_code'] }}</td>
                        <td>{{ $line['name'] }}</td>
                        <td class="capitalize text-xs">{{ $line['status'] }}</td>
                        <td class="text-xs">{{ $line['invoice_number'] ?? '—' }}</td>
                        <td class="text-right font-mono">{{ $line['retail_total'] > 0 ? number_format($line['retail_total'], 0) : '—' }}</td>
                        <td class="text-right font-mono {{ $line['retail_due'] > 0 ? 'rsl-due-breakdown__credit' : '' }}">
                            {{ $line['retail_due'] > 0 ? number_format($line['retail_due'], 0) : '—' }}
                        </td>
                        <td class="text-right font-mono {{ $line['wholesale'] > 0 ? 'rsl-due-breakdown__debit' : '' }}">
                            {{ $line['has_accrual'] ? number_format($line['wholesale'], 0) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right">Total</td>
                    <td class="text-right font-mono rsl-due-breakdown__credit">{{ number_format($lineTotals['retail_due'], 2) }}</td>
                    <td class="text-right font-mono rsl-due-breakdown__debit">{{ number_format($lineTotals['wholesale'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="rsl-panel overflow-hidden">
    <div class="rsl-panel-pad" style="border-bottom:1px solid var(--rsl-border)">
        <h2 class="rsl-due-panel__title rsl-due-panel__title--sm">HQ ledger (last 100)</h2>
        <p class="rsl-due-panel__sub" style="margin:0.35rem 0 0">+ increases due · − settlement / collection</p>
    </div>
    <div class="overflow-x-auto">
        <table class="rsl-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Change</th>
                    <th>Due after</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php $isIncrease = $entry->direction === 'debit'; @endphp
                    <tr class="{{ $isIncrease ? 'rsl-table-row--debit' : 'rsl-table-row--credit' }}">
                        <td class="whitespace-nowrap">{{ $entry->created_at->format('d M Y H:i') }}</td>
                        <td>{{ \App\Services\Resellers\ResellerDueLedgerService::entryTypeLabel($entry->entry_type) }}</td>
                        <td class="font-mono font-semibold {{ $isIncrease ? 'rsl-due-breakdown__debit' : 'rsl-due-breakdown__credit' }}">
                            {{ $isIncrease ? '+' : '−' }}{{ number_format((float) $entry->amount, 2) }}
                        </td>
                        <td class="font-mono font-bold">{{ number_format((float) $entry->admin_receivable_after, 2) }}</td>
                        <td class="rsl-text-muted max-w-xs truncate" title="{{ $entry->notes }}">{{ $entry->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center rsl-text-muted py-8">No ledger yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
