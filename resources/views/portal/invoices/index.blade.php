@extends('portal.layout')

@section('title', 'Invoices')

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">Invoices</h1>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Period</th>
                    <th class="px-4 py-3">Due date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Paid</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($invoices as $inv)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.invoices.show', $inv) }}" class="font-mono text-amber-700 hover:underline">{{ $inv->invoice_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->period_start?->format('M j') }} – {{ $inv->period_end?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->due_date?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $inv->amount_paid, 2) }}</td>
                        <td class="px-4 py-3 capitalize text-slate-700">{{ $inv->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $invoices->links() }}
    </div>
@endsection
