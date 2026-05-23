@extends('reseller.layout')

@section('title', 'Settlements')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="text-xl font-bold text-slate-900">Settlement requests</h1>
        <p class="mt-1 text-sm text-slate-600">Available for settlement: <strong>{{ number_format($outstanding, 2) }} BDT</strong> (wallet + pending commission − pending requests)</p>
    </div>

    <div class="rsl-card mt-6 p-6">
        <h2 class="font-semibold text-slate-900">New request</h2>
        <form method="post" action="{{ route('reseller.settlements.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Amount (BDT)</label>
                <input type="number" name="amount" step="0.01" min="1" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-base" />
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Expense deduction</label>
                <input type="number" name="expense_deduction" step="0.01" min="0" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-base" />
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Payment method</label>
                <input type="text" name="payment_method" placeholder="cash / bank" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500">Reference</label>
                <input type="text" name="reference" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2" />
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold uppercase text-slate-500">Notes</label>
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="rsl-btn">Submit for approval</button>
            </div>
        </form>
        @if ($errors->any())
            <ul class="mt-3 text-sm text-rose-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="rsl-card mt-6 overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="font-semibold text-slate-900">History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="rsl-table w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50">
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Net</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-mono text-xs">{{ $row->settlement_number }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $row->net_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 capitalize">{{ $row->statusLabel() }}</td>
                            <td class="px-4 py-3">{{ $row->submitted_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-slate-500">No settlement requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3">{{ $rows->links() }}</div>
    </div>
@endsection
