@extends('reseller.layout')

@section('title', 'Invoices')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Invoices',
        'subtitle' => $reseller->code.' · '.$eligibleSubscribers.' active subscribers',
    ])

    <div class="rsl-panel rsl-panel-pad">
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && ($bulkGenerateEnabled ?? true))
            <form method="post" action="{{ route('reseller.invoices.generate-all') }}" class="mb-4"
                  onsubmit="return confirm('Generate monthly bills for all active subscribers who do not already have a bill for this period?');">
                @csrf
                <button type="submit" class="rsl-btn">Generate all monthly bills</button>
            </form>
            <div class="rsl-callout rsl-callout--info mb-4">
                <p class="font-semibold">Monthly bill run</p>
                <p class="mt-1 text-sm">One bill per active subscriber this month. Skips if a bill exists. Postpaid may accrue HQ wholesale due.</p>
            </div>
        @endif
        <form method="get" class="rsl-toolbar">
            <select name="status" class="rsl-input" style="width:auto;min-width:10rem">
                <option value="">All statuses</option>
                @foreach (['open', 'partial', 'paid', 'void'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Filter</button>
        </form>
    </div>

    <div class="rsl-panel mt-4 overflow-hidden">
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
