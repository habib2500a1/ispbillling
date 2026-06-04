@extends('reseller.layout')

@section('title', 'Commission')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Commission ledger',
        'subtitle' => 'Pending '.number_format($totals['pending'], 2).' BDT · Paid '.number_format($totals['paid'], 2).' BDT',
    ])

    <div class="rsl-panel rsl-panel-pad">
        <div class="rsl-toolbar">
            @foreach (['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $key => $label)
                <a href="{{ route('reseller.commissions.index', $key ? ['status' => $key] : []) }}"
                   class="rsl-btn-sm {{ $status === $key ? '' : 'rsl-btn-sm--outline' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="get" action="{{ route('reseller.commissions.statement.pdf') }}" class="rsl-form-grid rsl-form-grid--2 mt-4 pt-4" style="border-top:1px solid var(--rsl-border);max-width:28rem">
            <div class="rsl-field">
                <label class="rsl-field-label">PDF from</label>
                <input type="date" name="from" value="{{ $pdfFrom }}" class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label">To</label>
                <input type="date" name="to" value="{{ $pdfTo }}" class="rsl-input">
            </div>
            @if ($status)
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div style="grid-column:1/-1">
                <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Download statement PDF</button>
            </div>
        </form>
    </div>

    <div class="rsl-panel mt-4">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
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
                            <td class="px-4 py-3">{{ number_format((float) $row->gross_amount, 2) }}</td>
                            <td class="px-4 py-3 font-semibold">{{ number_format((float) $row->commission_amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $row->payment?->method ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rsl-badge-pill rsl-badge-pill--muted">{{ $row->status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('reseller.commissions.show.pdf', $row) }}" class="rsl-link-action" target="_blank" rel="noopener">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($commissions->hasPages())
            <div class="p-4">{{ $commissions->links() }}</div>
        @endif
    </div>
@endsection
