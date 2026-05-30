@extends('reseller.layout')

@section('title', 'Reports')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Reports</h1>
        <form method="get" class="mt-4 flex flex-wrap gap-2 items-end">
            <div><label class="text-xs rsl-text-muted">From</label><input type="date" name="from" value="{{ $from }}" class="rsl-input mt-1"></div>
            <div><label class="text-xs rsl-text-muted">To</label><input type="date" name="to" value="{{ $to }}" class="rsl-input mt-1"></div>
            <button type="submit" class="rsl-btn-sm">Apply</button>
        </form>
    </div>
    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric"><p class="rsl-metric-label">Collection</p><p class="rsl-metric-value text-emerald-700">{{ number_format($collectionTotal, 0) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Commission</p><p class="rsl-metric-value text-violet-700">{{ number_format($commissionSummary['total_commission'], 0) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Due outstanding</p><p class="rsl-metric-value text-rose-700">{{ number_format($dueTotal, 0) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Clients</p><p class="rsl-metric-value">{{ $clientCount }}</p><p class="rsl-metric-sub">{{ $activeCount }} active</p></div>
    </div>
    <div class="rsl-card mt-6 p-6">
        <h2 class="rsl-heading mb-2">Export reports</h2>
        <p class="rsl-subtitle mb-4">CSV or Excel (.xlsx) — use the date range above for collection, commission and wallet.</p>
        @php
            $types = [
                'collection' => 'Collection',
                'commission' => 'Commission',
                'wallet' => 'Wallet',
                'due' => 'Due subscribers',
                'clients' => 'All subscribers',
            ];
        @endphp
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">Report</th>
                        <th class="px-3 py-2 text-left">CSV</th>
                        <th class="px-3 py-2 text-left">Excel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $key => $label)
                        <tr>
                            <td class="px-3 py-2 rsl-text">{{ $label }}</td>
                            <td class="px-3 py-2">
                                <a href="{{ route('reseller.reports.export', ['type' => $key, 'format' => 'csv', 'from' => $from, 'to' => $to]) }}" class="rsl-link">Download</a>
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('reseller.reports.export', ['type' => $key, 'format' => 'xlsx', 'from' => $from, 'to' => $to]) }}" class="rsl-link">Download</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-sm rsl-text-muted">
            Commission statement PDF:
            <a href="{{ route('reseller.commissions.statement.pdf', ['from' => $from, 'to' => $to]) }}" class="rsl-link">Download for selected period</a>
        </p>
    </div>
@endsection
