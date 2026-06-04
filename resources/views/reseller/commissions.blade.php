@extends('reseller.layout')

@section('title', 'Commissions')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Commission ledger</h1>
        <p class="rsl-subtitle mt-2">
            Pending: <strong class="text-amber-700">{{ number_format($totals['pending'], 2) }} BDT</strong>
            · Paid: <strong class="text-emerald-700">{{ number_format($totals['paid'], 2) }} BDT</strong>
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $key => $label)
                <a href="{{ route('reseller.commissions.index', $key ? ['status' => $key] : []) }}"
                   class="rsl-btn-sm {{ $status === $key ? '' : 'rsl-btn-sm--outline' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <form method="get" action="{{ route('reseller.commissions.statement.pdf') }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-200 pt-4">
            <div><label class="text-xs rsl-text-muted">PDF from</label><input type="date" name="from" value="{{ $pdfFrom }}" class="rsl-input mt-1"></div>
            <div><label class="text-xs rsl-text-muted">To</label><input type="date" name="to" value="{{ $pdfTo }}" class="rsl-input mt-1"></div>
            @if ($status)
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Download statement PDF</button>
        </form>
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Earned</th>
                        <th class="px-4 py-3">Subscriber</th>
                        <th class="px-4 py-3">Gross</th>
                        <th class="px-4 py-3">Commission</th>
                        <th class="px-4 py-3">Method</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">PDF</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($commissions as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->earned_at?->format('d M Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $row->gross_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 font-semibold text-emerald-700">{{ number_format((float) $row->commission_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 capitalize">{{ $row->payment?->method ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize">{{ $row->status }}</td>
                            <td class="px-4 py-3"><a href="{{ route('reseller.commissions.show.pdf', $row) }}" class="rsl-link text-xs" target="_blank" rel="noopener">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center rsl-text-muted">No commission records.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($commissions->hasPages())
            <div class="border-t border-slate-200 px-4 py-3">{{ $commissions->links() }}</div>
        @endif
    </div>
@endsection
