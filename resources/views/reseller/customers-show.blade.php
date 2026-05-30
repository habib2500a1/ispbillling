@extends('reseller.layout')

@section('title', $customer->name)

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">{{ $customer->name }}</h1>
        <p class="rsl-subtitle">{{ $customer->customer_code }} · {{ $customer->phone }} · {{ $customer->area?->name ?? '—' }}</p>
        <div class="mt-4 flex flex-wrap gap-2">
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <a href="{{ route('reseller.customers.edit', $customer) }}" class="rsl-btn-sm rsl-btn-sm--outline">Edit</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <a href="{{ route('reseller.customers.collect', $customer) }}" class="rsl-btn-sm">Collect payment</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE))
                <form method="post" action="{{ route('reseller.customers.invoice.generate', $customer) }}" class="inline">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Generate invoice</button></form>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <form method="post" action="{{ route('reseller.customers.renew', $customer) }}" class="inline">@csrf<input type="hidden" name="days" value="30"><button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Renew 30d</button></form>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_SUSPEND))
                @if ($customer->status !== 'suspended')
                    <form method="post" action="{{ route('reseller.customers.suspend', $customer) }}" class="inline" onsubmit="return confirm('Suspend this subscriber?')">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Suspend</button></form>
                @else
                    <form method="post" action="{{ route('reseller.customers.reconnect', $customer) }}" class="inline">@csrf<button type="submit" class="rsl-btn-sm">Reconnect</button></form>
                @endif
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::ONU_VIEW))
                <a href="{{ route('reseller.onu.show', $customer) }}" class="rsl-btn-sm rsl-btn-sm--outline">ONU</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::NETWORK_VIEW))
                <form method="post" action="{{ route('reseller.network.disconnect', $customer) }}" class="inline" onsubmit="return confirm('Disconnect active session?')">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Kick PPPoE</button></form>
            @endif
        </div>
    </div>

    <div class="rsl-kpi-grid mt-6">
        <div class="rsl-metric"><p class="rsl-metric-label">Package</p><p class="rsl-metric-value text-base">{{ $customer->package?->name ?? '—' }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Billing</p><p class="rsl-metric-value text-base capitalize">{{ $customer->billing_mode ?? 'prepaid' }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">PPPoE user</p><p class="rsl-metric-value text-base font-mono">{{ $customer->mikrotik_secret_name ?: '—' }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Status</p><p class="rsl-metric-value text-base capitalize">{{ $customer->status }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Due</p><p class="rsl-metric-value text-rose-700">{{ number_format($customer->openInvoiceBalance(), 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Expires</p><p class="rsl-metric-value text-base">{{ $customer->service_expires_at?->format('d M Y') ?? '—' }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Network</p><p class="rsl-metric-value text-base">{{ $customer->is_ppp_online ? 'Online' : 'Offline' }}</p></div>
    </div>

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::NETWORK_VIEW) && ! empty($networkSession))
        <div class="rsl-card mt-6 p-6">
            <h2 class="rsl-heading">PPPoE session</h2>
            <div class="rsl-kpi-grid mt-4">
                <div class="rsl-metric"><p class="rsl-metric-label">IP address</p><p class="rsl-metric-value text-base font-mono">{{ $networkSession['framed_ip'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Uptime</p><p class="rsl-metric-value text-base">{{ $networkSession['uptime'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Live download</p><p class="rsl-metric-value text-emerald-700 text-base">{{ $networkSession['download_human'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Live upload</p><p class="rsl-metric-value text-sky-700 text-base">{{ $networkSession['upload_human'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Session data</p><p class="rsl-metric-value text-base text-sm">↓ {{ $networkSession['session_download'] ?? '—' }} · ↑ {{ $networkSession['session_upload'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Today</p><p class="rsl-metric-value text-base text-sm">↓ {{ $networkSession['today_download'] ?? '—' }} · ↑ {{ $networkSession['today_upload'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">This month</p><p class="rsl-metric-value text-base text-sm">↓ {{ $networkSession['month_download'] ?? '—' }} · ↑ {{ $networkSession['month_upload'] ?? '—' }}</p></div>
                <div class="rsl-metric"><p class="rsl-metric-label">Router</p><p class="rsl-metric-value text-base">{{ $networkSession['router'] ?? '—' }}</p></div>
            </div>
            @if (! ($networkSession['online'] ?? false) && ! empty($networkSession['last_disconnect']))
                <p class="mt-3 text-sm rsl-text-muted">Last disconnect: {{ $networkSession['last_disconnect'] }}</p>
            @endif
        </div>
    @endif

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
        <div class="rsl-card mt-6 p-6 max-w-md">
            <h2 class="rsl-heading mb-3">Change PPPoE password</h2>
            <form method="post" action="{{ route('reseller.customers.password', $customer) }}" class="flex gap-2">
                @csrf
                <input type="password" name="password" required minlength="4" class="rsl-input flex-1" placeholder="New password">
                <button type="submit" class="rsl-btn-sm">Update</button>
            </form>
        </div>
    @endif

    <div class="grid gap-6 mt-6 lg:grid-cols-2">
        <div class="rsl-card overflow-hidden">
            <div class="rsl-card-header"><h2 class="rsl-heading">Payment history</h2></div>
            <div class="overflow-x-auto">
                <table class="rsl-table w-full text-sm">
                    <thead><tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Amount</th><th class="px-4 py-2">Method</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody>
                        @forelse ($payments as $pay)
                            <tr>
                                <td class="px-4 py-2 rsl-text">{{ $pay->paid_at?->format('d M Y') }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $pay->amount, 2) }}</td>
                                <td class="px-4 py-2 capitalize">{{ $pay->method }}</td>
                                <td class="px-4 py-2"><a href="{{ route('reseller.payments.receipt', $pay) }}" class="rsl-link" target="_blank">PDF</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center rsl-text-muted">No payments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="rsl-card overflow-hidden">
            <div class="rsl-card-header"><h2 class="rsl-heading">Invoice history</h2></div>
            <div class="overflow-x-auto">
                <table class="rsl-table w-full text-sm">
                    <thead><tr><th class="px-4 py-2">Invoice</th><th class="px-4 py-2">Total</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr>
                                <td class="px-4 py-2 rsl-text">{{ $inv->invoice_number }}</td>
                                <td class="px-4 py-2">{{ number_format((float) $inv->total, 2) }}</td>
                                <td class="px-4 py-2 capitalize">{{ $inv->status }}</td>
                                <td class="px-4 py-2"><a href="{{ route('reseller.invoices.pdf', $inv) }}" class="rsl-link" target="_blank">PDF</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center rsl-text-muted">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
