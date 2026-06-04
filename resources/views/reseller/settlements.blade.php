@extends('reseller.layout')

@section('title', 'Settlements')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Settlement requests',
        'subtitle' => 'Withdrawable: '.number_format($outstanding, 2).' BDT (wallet + pending commission − pending requests)',
    ])

    <div class="rsl-panel rsl-panel-pad">
        <h2 class="rsl-panel-title">New request</h2>
        <form method="post" action="{{ route('reseller.settlements.store') }}" class="rsl-form-grid rsl-form-grid--2 mt-4">
            @csrf
            <div class="rsl-field">
                <label class="rsl-field-label" for="amount">Amount (BDT)</label>
                <input id="amount" type="number" name="amount" step="0.01" min="1" required class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="expense_deduction">Expense deduction</label>
                <input id="expense_deduction" type="number" name="expense_deduction" step="0.01" min="0" value="0" class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="payment_method">Payment method</label>
                <input id="payment_method" type="text" name="payment_method" placeholder="cash / bank" class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="reference">Reference</label>
                <input id="reference" type="text" name="reference" class="rsl-input">
            </div>
            <div class="rsl-field" style="grid-column:1/-1">
                <label class="rsl-field-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="rsl-input"></textarea>
            </div>
            <div style="grid-column:1/-1">
                <button type="submit" class="rsl-btn">Submit for approval</button>
            </div>
        </form>
        @if ($errors->any())
            <ul class="mt-3 text-sm" style="color:var(--rsl-danger)">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="rsl-panel">
        <div class="rsl-panel-head">
            <h2 class="rsl-panel-title">History</h2>
        </div>
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-left text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Net</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $row->settlement_number }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $row->net_amount, 2) }} BDT</td>
                            <td class="px-4 py-3 capitalize">{{ $row->statusLabel() }}</td>
                            <td class="px-4 py-3">{{ $row->submitted_at?->format('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="rsl-panel-pad border-t" style="border-color:var(--rsl-border)">{{ $rows->links() }}</div>
        @endif
    </div>
@endsection
