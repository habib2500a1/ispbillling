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
    @php
        $balanceDue = max(0, (float) $invoice->total - (float) $invoice->amount_paid);
    @endphp

    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric"><p class="rsl-metric-label">Total</p><p class="rsl-metric-value text-base">{{ number_format((float) $invoice->total, 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Paid</p><p class="rsl-metric-value text-base">{{ number_format((float) $invoice->amount_paid, 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Due</p><p class="rsl-metric-value text-rose-700">{{ number_format($balanceDue, 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Status</p><p class="rsl-metric-value text-base capitalize">{{ $invoice->status }}</p></div>
        @if ((float) $invoice->discount_amount > 0)
            <div class="rsl-metric"><p class="rsl-metric-label">Discount</p><p class="rsl-metric-value text-amber-700">{{ number_format((float) $invoice->discount_amount, 2) }} BDT</p></div>
        @endif
    </div>

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_ADJUST) && $balanceDue > 0 && ! in_array($invoice->status, ['paid', 'void', 'cancelled']))
        <div class="rsl-card mt-6 p-6">
            <h2 class="rsl-heading">Discount / waive</h2>
            <p class="rsl-subtitle mt-1">Reduce bill for this subscriber. HQ wholesale due is not changed automatically.</p>
            <form method="post" action="{{ route('reseller.invoices.adjust', $invoice) }}" class="mt-4 grid gap-4 max-w-md">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Discount amount (BDT)</label>
                    <input type="number" name="discount_amount" step="0.01" min="0" max="{{ $balanceDue }}" value="{{ old('discount_amount', $balanceDue) }}" class="rsl-input mt-1">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="waive_full" value="1" class="rounded border-slate-300">
                    Waive full remaining balance
                </label>
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Reason</label>
                    <input name="reason" maxlength="500" class="rsl-input mt-1" placeholder="Optional note for audit">
                </div>
                <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Apply adjustment</button>
            </form>
        </div>
    @endif

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::BILLING_VIEW) && $balanceDue > 0)
        <div class="rsl-card mt-6 p-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="rsl-heading text-sm">Due reminder</h2>
                <p class="rsl-subtitle mt-1">SMS/email to subscriber (once per 24h per bill).</p>
            </div>
            <form method="post" action="{{ route('reseller.invoices.due-reminder', $invoice) }}">
                @csrf
                <button type="submit" class="rsl-btn-sm">Send due reminder</button>
            </form>
        </div>
    @endif

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
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left">Item</th>
                        <th class="px-4 py-3">Qty</th>
                        <th class="px-4 py-3">Price</th>
                        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_EDIT) && ! in_array($invoice->status, ['paid', 'void', 'cancelled']))
                            <th class="px-4 py-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="px-4 py-3">{{ $item->description }}</td>
                            <td class="px-4 py-3 text-center">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_EDIT) && ! in_array($invoice->status, ['paid', 'void', 'cancelled']))
                                <td class="px-4 py-3">
                                    <form method="post" action="{{ route('reseller.invoices.lines.update', $invoice) }}" class="flex flex-wrap items-end gap-2 justify-end">
                                        @csrf
                                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                                        <input type="number" name="unit_price" step="0.01" min="0" value="{{ number_format((float) $item->unit_price, 2, '.', '') }}" class="rsl-input w-24 text-sm py-1">
                                        <input type="hidden" name="description" value="{{ $item->description }}">
                                        <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline text-xs">Save</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_EDIT) && ! in_array($invoice->status, ['paid', 'void', 'cancelled']))
        <div class="rsl-card mt-6 p-6">
            <h2 class="rsl-heading">Add adjustment line</h2>
            <p class="rsl-subtitle mt-1">Positive or negative amount (e.g. -50 for credit). Totals recalculate automatically.</p>
            <form method="post" action="{{ route('reseller.invoices.lines.add', $invoice) }}" class="mt-4 grid gap-4 max-w-md">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Description</label>
                    <input name="description" required maxlength="255" class="rsl-input mt-1" placeholder="e.g. Late fee waiver">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase rsl-text-muted">Amount (BDT)</label>
                    <input type="number" name="amount" step="0.01" required class="rsl-input mt-1" placeholder="-100 or 50">
                </div>
                <button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Add line</button>
            </form>
        </div>
    @endif
@endsection
