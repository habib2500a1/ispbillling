@extends('reseller.layout')

@section('title', 'Customer transfers')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Customer transfers</h1>
        <p class="rsl-subtitle">Transfer requests and ownership history.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Requested</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $t)
                        <tr>
                            <td>{{ $t->customer?->customer_code }} {{ $t->customer?->name }}</td>
                            <td>{{ $t->fromReseller?->code }}</td>
                            <td>{{ $t->toReseller?->code }}</td>
                            <td>{{ $t->status }}</td>
                            <td>{{ $t->requested_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 rsl-text-muted">No transfers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
