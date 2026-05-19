@extends('portal.layout')

@section('title', 'Payments')

@section('content')
    <h1 class="text-2xl font-semibold text-slate-900">Payments</h1>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Receipt</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Method</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Receipt PDF</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($payments as $payment)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $payment->receipt_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $payment->paid_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($payment->invoice)
                                <a href="{{ route('portal.invoices.show', $payment->invoice) }}" class="font-mono text-amber-700 hover:underline">{{ $payment->invoice->invoice_number }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $payment->methodLabel() }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $payment->typeLabel() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium {{ $payment->isRefund() ? 'text-red-600' : 'text-slate-900' }}">
                            {{ $payment->isRefund() ? '−' : '' }}{{ number_format((float) $payment->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 capitalize text-slate-700">{{ $payment->status }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($payment->status === 'completed')
                                <a
                                    href="{{ route('portal.payments.receipt', $payment) }}"
                                    class="inline-flex items-center rounded-md bg-amber-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm hover:bg-amber-700"
                                >
                                    Receipt
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">No payments recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $payments->links() }}
    </div>
@endsection
