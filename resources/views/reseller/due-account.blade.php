@extends('reseller.layout')

@section('title', 'Due account')

@section('content')
@php
    $s = $summary;
    $c = $customerBreakdown;
@endphp
@include('reseller.partials.page-header', [
    'title' => 'Due account',
    'subtitle' => 'HQ due (wholesale) and subscriber due (retail) — reduced by payments.',
])

{{-- Side by side: HQ vs Customer --}}
<div class="grid gap-6 lg:grid-cols-2 mb-6">
    {{-- Admin / HQ side --}}
    <div class="rsl-panel rsl-panel-pad" style="border-color:rgba(244,63,94,0.35)">
        <h2 class="text-lg font-bold text-rose-800">① Admin (HQ) — your balance due</h2>
        <p class="text-sm text-slate-600 mt-1">Wholesale @ 330 BDT/subscriber (per package). Reduced by settlement/adjustment.</p>

        <div class="mt-4 rounded-lg bg-rose-50 p-4 text-center">
            <p class="text-xs font-semibold uppercase text-rose-700">Current HQ due</p>
            <p class="text-3xl font-bold text-rose-800 mt-1">{{ number_format($s['admin_due'], 2) }} BDT</p>
        </div>

        <dl class="mt-4 space-y-2 text-sm border-t border-rose-100 pt-4">
            <div class="flex justify-between gap-2">
                <dt class="text-slate-600">+ Wholesale Bills (accrual)</dt>
                <dd class="font-mono text-rose-700">+{{ number_format($s['total_wholesale_accrued'] ?? 0, 2) }}</dd>
            </div>
            @if (($s['debit_notes'] ?? 0) > 0)
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-600">+ Debit note (admin added)</dt>
                    <dd class="font-mono text-rose-700">+{{ number_format($s['debit_notes'], 2) }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-2 font-medium border-t border-slate-100 pt-2">
                <dt>Total increases</dt>
                <dd class="font-mono">{{ number_format($s['total_debits'] ?? 0, 2) }} BDT</dd>
            </div>
            <div class="flex justify-between gap-2 text-emerald-800">
                <dt>− Paid to HQ (settlement)</dt>
                <dd class="font-mono">−{{ number_format($s['paid_to_hq_settlement'] ?? 0, 2) }}</dd>
            </div>
            <div class="flex justify-between gap-2 text-emerald-800">
                <dt>− Customer payment (HQ share)</dt>
                <dd class="font-mono">−{{ number_format($s['reduced_from_customer_payments'] ?? 0, 2) }}</dd>
            </div>
            @if (($s['credit_notes'] ?? 0) > 0)
                <div class="flex justify-between gap-2 text-emerald-800">
                    <dt>− Credit note / adjustment</dt>
                    <dd class="font-mono">−{{ number_format($s['credit_notes'], 2) }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-2 font-bold border-t border-slate-200 pt-2">
                <dt>= Balance due (calc)</dt>
                <dd class="font-mono text-rose-800">{{ number_format($s['calculated_due'] ?? $s['admin_due'], 2) }} BDT</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-slate-500">Your margin: {{ number_format($s['margin_total'], 2) }} BDT (from customer collections)</p>
    </div>

    {{-- Reseller / customer side --}}
    <div class="rsl-panel p-5 border-2 border-emerald-200">
        <h2 class="text-lg font-bold text-emerald-800">② Reseller — customer balances due</h2>
        <p class="text-sm text-slate-600 mt-1">Subscriber retail bills. Due drops when you collect or discount.</p>

        <div class="mt-4 rounded-lg bg-emerald-50 p-4 text-center">
            <p class="text-xs font-semibold uppercase text-emerald-700">Current customer due</p>
            <p class="text-3xl font-bold text-emerald-800 mt-1">{{ number_format($c['due'], 2) }} BDT</p>
        </div>

        <dl class="mt-4 space-y-2 text-sm border-t border-emerald-100 pt-4">
            <div class="flex justify-between gap-2">
                <dt class="text-slate-600">Total invoiced</dt>
                <dd class="font-mono">{{ number_format($c['invoiced'], 2) }} BDT</dd>
            </div>
            <div class="flex justify-between gap-2 text-emerald-800">
                <dt>− Collected</dt>
                <dd class="font-mono">−{{ number_format($c['collected'], 2) }}</dd>
            </div>
            @if ($c['discounted'] > 0)
                <div class="flex justify-between gap-2 text-amber-800">
                    <dt>− Discount / waive</dt>
                    <dd class="font-mono">(on invoice {{ number_format($c['discounted'], 2) }})</dd>
                </div>
            @endif
            <div class="flex justify-between gap-2 font-bold border-t border-slate-200 pt-2">
                <dt>= Due</dt>
                <dd class="font-mono text-emerald-800">{{ number_format($c['due'], 2) }} BDT</dd>
            </div>
        </dl>
        <p class="mt-3">
            <a href="{{ route('reseller.customers.index', ['due' => 1]) }}" class="rsl-link text-sm">Due subscribers List →</a>
        </p>
    </div>
</div>

<div class="grid gap-4 md:grid-cols-2 mb-6">
    <div class="rsl-panel p-4">
        <h2 class="font-semibold text-slate-800 mb-2">What reduces due?</h2>
        <ul class="text-sm space-y-2 text-slate-700 list-disc pl-5">
            <li><strong>HQ due:</strong> Admin records settlement · Customer payment (HQ share) · Credit note</li>
            <li><strong>Customer due:</strong> Collect payment · Invoice discount/waive</li>
            <li><strong>HQ due increases:</strong> New monthly bill generate (wholesale accrual)</li>
        </ul>
    </div>
    <div class="rsl-panel p-4">
        <h2 class="font-semibold text-slate-800 mb-2">Billing policy</h2>
        <dl class="text-sm space-y-1">
            <div class="flex justify-between"><dt class="text-slate-500">Settlement</dt><dd>{{ $settlementMode }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Credit limit</dt><dd>{{ number_format($s['credit_limit'], 0) }} BDT ({{ $s['utilization_percent'] }}%)</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Customer policy</dt><dd>{{ $customerPolicy }}</dd></div>
        </dl>
    </div>
</div>

<div class="rsl-panel p-4 mb-6">
    <h2 class="font-semibold text-slate-800 mb-2">Customer due aging (owed to you)</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
        <div><span class="text-slate-500">0–30d</span><br><strong>{{ number_format($aging['bucket_30'], 0) }}</strong></div>
        <div><span class="text-slate-500">31–60d</span><br><strong>{{ number_format($aging['bucket_60'], 0) }}</strong></div>
        <div><span class="text-slate-500">61–90d</span><br><strong>{{ number_format($aging['bucket_90'], 0) }}</strong></div>
        <div><span class="text-slate-500">90+d</span><br><strong>{{ number_format($aging['bucket_90_plus'], 0) }}</strong></div>
    </div>
</div>

<div class="rsl-panel overflow-hidden mb-6">
    <div class="px-4 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-semibold text-slate-800">Subscriber lines — {{ $lineTotals['count'] }} subscribers</h2>
            <p class="text-xs text-slate-500 mt-1">
                {{ $lineTotals['with_bill'] }} subscribers billed this month · Customer due total {{ number_format($lineTotals['retail_due'], 2) }} BDT
                · HQ wholesale Total {{ number_format($lineTotals['wholesale'], 2) }} BDT
            </p>
        </div>
    </div>
    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600 sticky top-0">
                <tr>
                    <th class="px-3 py-2">#</th>
                    <th class="px-3 py-2">Code</th>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Bill</th>
                    <th class="px-3 py-2 text-right">Retail</th>
                    <th class="px-3 py-2 text-right">Customer due</th>
                    <th class="px-3 py-2 text-right">HQ (330)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subscriberLines as $i => $line)
                    <tr class="border-t border-slate-100 {{ ! $line['has_accrual'] && $line['status'] !== 'active' ? 'bg-slate-50 text-slate-500' : '' }}">
                        <td class="px-3 py-2">{{ $i + 1 }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $line['customer_code'] }}</td>
                        <td class="px-3 py-2">{{ $line['name'] }}</td>
                        <td class="px-3 py-2 capitalize text-xs">{{ $line['status'] }}</td>
                        <td class="px-3 py-2 text-xs">{{ $line['invoice_number'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $line['retail_total'] > 0 ? number_format($line['retail_total'], 0) : '—' }}</td>
                        <td class="px-3 py-2 text-right font-mono {{ $line['retail_due'] > 0 ? 'text-emerald-700 font-semibold' : '' }}">
                            {{ $line['retail_due'] > 0 ? number_format($line['retail_due'], 0) : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right font-mono {{ $line['wholesale'] > 0 ? 'text-rose-700' : '' }}">
                            {{ $line['has_accrual'] ? number_format($line['wholesale'], 0) : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-slate-100 font-semibold border-t-2 border-slate-300">
                <tr>
                    <td colspan="6" class="px-3 py-2 text-right">Total ({{ $lineTotals['count'] }} lines)</td>
                    <td class="px-3 py-2 text-right font-mono text-emerald-800">{{ number_format($lineTotals['retail_due'], 2) }}</td>
                    <td class="px-3 py-2 text-right font-mono text-rose-800">{{ number_format($lineTotals['wholesale'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="rsl-panel overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200">
        <h2 class="font-semibold text-slate-800">HQ ledger — “Due after” per line</h2>
        <p class="text-xs text-slate-500 mt-1">+ = due up · − = due down (settlement / payment / adjustment)</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-600">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2">Change</th>
                    <th class="px-4 py-2">Due after</th>
                    <th class="px-4 py-2">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php
                        $isIncrease = $entry->direction === 'debit';
                    @endphp
                    <tr class="border-t border-slate-100 {{ $isIncrease ? 'bg-rose-50/40' : 'bg-emerald-50/40' }}">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $entry->created_at->format('d M Y H:i') }}</td>
                        <td class="px-4 py-2">{{ \App\Services\Resellers\ResellerDueLedgerService::entryTypeLabel($entry->entry_type) }}</td>
                        <td class="px-4 py-2 font-mono font-semibold {{ $isIncrease ? 'text-rose-700' : 'text-emerald-700' }}">
                            {{ $isIncrease ? '+' : '−' }}{{ number_format((float) $entry->amount, 2) }}
                        </td>
                        <td class="px-4 py-2 font-mono font-bold">{{ number_format((float) $entry->admin_receivable_after, 2) }}</td>
                        <td class="px-4 py-2 text-slate-600 max-w-xs truncate" title="{{ $entry->notes }}">{{ $entry->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No ledger yet — wholesale accruals appear when you generate bills.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
