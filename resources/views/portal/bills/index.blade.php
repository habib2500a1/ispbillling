@extends('portal.layout')

@section('title', 'My bills')

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-violet-800">My bills</h1>
            <p class="mt-1 text-sm text-slate-600">View, download, and pay your invoices online.</p>
        </div>
        @if ($totalDue > 0)
            <div class="rounded-xl bg-gradient-to-r from-rose-500 to-orange-500 px-5 py-3 text-white shadow-lg">
                <p class="text-xs font-semibold uppercase opacity-90">Total outstanding</p>
                <p class="text-2xl font-bold tabular-nums">{{ number_format($totalDue, 2) }} BDT</p>
            </div>
        @endif
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-gradient-to-r from-violet-50 to-fuchsia-50 text-left text-xs font-bold uppercase text-violet-800">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Due date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $inv)
                    @php $due = round((float) $inv->total - (float) $inv->amount_paid, 2); @endphp
                    <tr class="hover:bg-violet-50/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('portal.invoices.show', $inv) }}" class="font-mono font-semibold text-violet-600 hover:underline">{{ $inv->invoice_number }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $inv->due_date?->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $inv->total, 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums {{ $due > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($due, 2) }}</td>
                        <td class="px-4 py-3 capitalize">{{ $inv->status }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <a href="{{ route('portal.invoices.pdf', $inv) }}" class="rounded-lg border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">PDF</a>
                                @if ($due > 0 && ($gateways['any'] ?? false) && ! in_array($inv->status, ['void', 'cancelled'], true))
                                    @include('portal.partials.pay-buttons', ['invoice' => $inv, 'amount' => $due, 'size' => 'sm'])
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No bills found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
