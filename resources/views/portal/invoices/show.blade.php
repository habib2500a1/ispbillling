@extends('portal.layout')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase text-violet-600">Bill / Invoice</p>
            <h1 class="font-mono text-2xl font-bold text-slate-900">{{ $invoice->invoice_number }}</h1>
            <p class="mt-1 capitalize text-slate-600">Status: <span class="font-semibold">{{ $invoice->status }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($canPay)
                @include('portal.partials.pay-buttons', ['invoice' => $invoice, 'amount' => $balanceDue])
            @endif
            <a href="{{ route('portal.invoices.pdf', $invoice) }}" class="inline-flex items-center rounded-xl border-2 border-violet-200 bg-violet-50 px-4 py-2 text-sm font-bold text-violet-800 hover:bg-violet-100">
                Download invoice PDF
            </a>
            <a href="{{ route('portal.bills.index') }}" class="inline-flex items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">← All bills</a>
        </div>
    </div>

    <dl class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-gradient-to-br from-violet-50 to-white p-4 ring-1 ring-violet-100">
            <dt class="text-xs font-bold uppercase text-violet-600">Issue date</dt>
            <dd class="mt-1 font-semibold">{{ $invoice->issue_date?->format('M j, Y') }}</dd>
        </div>
        <div class="rounded-xl bg-gradient-to-br from-amber-50 to-white p-4 ring-1 ring-amber-100">
            <dt class="text-xs font-bold uppercase text-amber-700">Due date</dt>
            <dd class="mt-1 font-semibold">{{ $invoice->due_date?->format('M j, Y') }}</dd>
        </div>
        <div class="rounded-xl bg-gradient-to-br from-slate-50 to-white p-4 ring-1 ring-slate-200">
            <dt class="text-xs font-bold uppercase text-slate-500">Total</dt>
            <dd class="mt-1 text-lg font-bold tabular-nums">{{ number_format((float) $invoice->total, 2) }} BDT</dd>
        </div>
        <div class="rounded-xl bg-gradient-to-br from-rose-50 to-white p-4 ring-1 ring-rose-200">
            <dt class="text-xs font-bold uppercase text-rose-600">Amount due</dt>
            <dd class="mt-1 text-lg font-bold tabular-nums text-rose-700">{{ number_format($balanceDue, 2) }} BDT</dd>
        </div>
    </dl>

    @if ($invoice->items->isNotEmpty())
        <h2 class="mt-10 text-lg font-bold text-slate-800">Line items</h2>
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
            <table class="min-w-full text-sm">
                <thead class="bg-violet-50 text-left text-xs font-bold uppercase text-violet-800">
                    <tr>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Unit</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($invoice->items as $line)
                        <tr>
                            <td class="px-4 py-3">{{ $line->description }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $line->quantity, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $line->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format((float) $line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
