@extends('reseller.layout')

@section('title', $invoice->invoice_number)

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">{{ $invoice->invoice_number }}</h1>
        <p class="rsl-subtitle">{{ $invoice->customer?->name }} · {{ $invoice->customer?->customer_code }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('reseller.invoices.pdf', $invoice) }}" class="rsl-btn-sm" target="_blank">Download PDF</a>
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <a href="{{ route('reseller.customers.collect', $invoice->customer) }}" class="rsl-btn-sm rsl-btn-sm--outline">Collect payment</a>
            @endif
        </div>
    </div>
    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric"><p class="rsl-metric-label">Total</p><p class="rsl-metric-value text-base">{{ number_format((float) $invoice->total, 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Paid</p><p class="rsl-metric-value text-base">{{ number_format((float) $invoice->amount_paid, 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Due</p><p class="rsl-metric-value text-rose-700">{{ number_format(max(0, (float) $invoice->total - (float) $invoice->amount_paid), 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Status</p><p class="rsl-metric-value text-base capitalize">{{ $invoice->status }}</p></div>
    </div>

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::BILLING_VIEW) && ($notifyChannels['sms'] || $notifyChannels['email']))
        <div class="rsl-card mt-6 p-6">
            <h2 class="rsl-heading">Send to subscriber</h2>
            <p class="rsl-subtitle mt-1">Invoice details with optional online payment link.</p>
            <form method="post" action="{{ route('reseller.invoices.send', $invoice) }}" class="mt-4 space-y-3 max-w-lg">
                @csrf
                <div class="flex flex-wrap gap-4">
                    @if ($notifyChannels['sms'])
                        <label class="flex items-center gap-2 text-sm rsl-text">
                            <input type="checkbox" name="channels[]" value="sms" checked class="rounded border-slate-300">
                            SMS ({{ $invoice->customer?->phone }})
                        </label>
                    @endif
                    @if ($notifyChannels['email'])
                        <label class="flex items-center gap-2 text-sm rsl-text">
                            <input type="checkbox" name="channels[]" value="email" @if(! $notifyChannels['sms']) checked @endif class="rounded border-slate-300">
                            Email ({{ $invoice->customer?->email }})
                        </label>
                    @endif
                </div>
                @if (max(0, (float) $invoice->total - (float) $invoice->amount_paid) > 0)
                    <label class="flex items-center gap-2 text-sm rsl-text">
                        <input type="checkbox" name="include_payment_link" value="1" checked class="rounded border-slate-300">
                        Include online payment link
                    </label>
                @endif
                <button type="submit" class="rsl-btn-sm">Send invoice</button>
            </form>
        </div>
    @elseif ($portal->canPortal(\App\Support\ResellerPortalPermission::BILLING_VIEW))
        <div class="rsl-card mt-6 p-6">
            <p class="text-sm rsl-text-muted">Add subscriber phone or email (and enable SMS gateway) to send this invoice.</p>
        </div>
    @endif

    @if ($invoice->items->isNotEmpty())
        <div class="rsl-card mt-6 overflow-hidden">
            <table class="rsl-table w-full text-sm">
                <thead><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Qty</th><th class="px-4 py-3">Price</th></tr></thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr><td class="px-4 py-3">{{ $item->description }}</td><td class="px-4 py-3">{{ $item->quantity }}</td><td class="px-4 py-3">{{ number_format((float) $item->unit_price, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
