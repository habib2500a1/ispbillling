@extends('reseller.layout')

@section('title', 'Invoices')

@section('content')
    <div class="rsl-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="rsl-title">Invoices</h1>
                <p class="rsl-subtitle mt-1">Partner {{ $reseller->code }} · {{ $eligibleSubscribers }} active subscribers</p>
            </div>
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && ($bulkGenerateEnabled ?? true))
                <form method="post" action="{{ route('reseller.invoices.generate-all') }}"
                      onsubmit="return confirm('Generate monthly bills for all active subscribers who do not already have a bill for this period?');">
                    @csrf
                    <button type="submit" class="rsl-btn">Generate all monthly bills</button>
                </form>
            @endif
        </div>

        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && ($bulkGenerateEnabled ?? true))
            <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">
                <p class="font-semibold">Monthly bill run</p>
                <p class="mt-1 text-indigo-900">
                    Creates one invoice per active subscriber for the current billing period (package price, proration rules, ONU lines).
                    Subscribers who already have a bill this month are skipped.
                    HQ wholesale due accrues when your account uses postpaid settlement.
                </p>
            </div>
        @endif

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
