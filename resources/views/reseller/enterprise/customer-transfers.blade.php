@extends('reseller.layout')

@section('title', 'Customer transfers')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Customer transfers',
        'subtitle' => 'Requests and ownership history.',
    ])

    <div class="rsl-panel">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Subscribers</th>
                        <th class="px-4 py-3">From</th>
                        <th class="px-4 py-3">To</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $t)
                        <tr>
                            <td class="px-4 py-3">{{ $t->customer?->customer_code }} {{ $t->customer?->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $t->fromReseller?->code }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $t->toReseller?->code }}</td>
                            <td class="px-4 py-3">
                                <span class="rsl-badge-pill rsl-badge-pill--muted">{{ $t->status }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $t->requested_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No transfers yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
