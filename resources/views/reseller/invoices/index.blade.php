@extends('reseller.layout')

@section('title', 'Invoices')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Invoices</h1>
        <form method="get" class="mt-4 flex flex-wrap gap-2">
            <select name="status" class="rsl-input w-auto">
                <option value="">All statuses</option>
                @foreach (['open', 'partial', 'paid', 'void'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rsl-btn-sm">Filter</button>
        </form>
    </div>
    <div class="rsl-card mt-6 overflow-hidden">
        <table class="rsl-table w-full text-sm">
            <thead><tr><th class="px-4 py-3">Invoice</th><th class="px-4 py-3">Subscriber</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Paid</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody>
                @forelse ($invoices as $inv)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('reseller.invoices.show', $inv) }}" class="rsl-link">{{ $inv->invoice_number }}</a></td>
                        <td class="px-4 py-3 rsl-text">{{ $inv->customer?->name }}<br><span class="text-xs rsl-text-muted">{{ $inv->customer?->customer_code }}</span></td>
                        <td class="px-4 py-3">{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $inv->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 capitalize">{{ $inv->status }}</td>
                        <td class="px-4 py-3"><a href="{{ route('reseller.invoices.pdf', $inv) }}" class="rsl-link" target="_blank">PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center rsl-text-muted">No invoices found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $invoices->links() }}</div>
    </div>
@endsection
