@extends('reseller.layout')

@section('title', $customer->name)

@section('content')
    @php
        $resellerHold = is_array($customer->meta['reseller_hold'] ?? null) ? $customer->meta['reseller_hold'] : null;
        $openDue = $displayDue ?? $customer->openInvoiceBalance();
        $statusNote = ($billingPaused ?? false)
            ? (($suspensionMonthCurrent ?? false) ? "Suspended — This month's bill exists" : 'Suspended — new month, no bill')
            : ($openDue > 0 ? 'Due: '.number_format($openDue, 2).' BDT' : null);
    @endphp

    @include('reseller.partials.page-header', [
        'title' => $customer->name,
        'subtitle' => $customer->customer_code.' · '.$customer->phone.' · '.($customer->area?->name ?? '—').($statusNote ? ' · '.$statusNote : ''),
        'backUrl' => route('reseller.customers.index'),
        'backLabel' => '← Customers',
    ])

    <div class="rsl-panel rsl-panel-pad">
        @if ($billingPaused ?? false)
            @if ($suspensionMonthCurrent ?? false)
                <p class="mt-2 inline-flex rounded-lg bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">
                    Suspended — bill exists for this month (suspended after generate). No new bill next month.
                </p>
            @else
                <p class="mt-2 inline-flex rounded-lg bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-700">
                    Suspended — new month, no bill. Reconnect with bill.
                </p>
            @endif
        @elseif ($openDue > 0)
            <p class="mt-2 inline-flex rounded-lg bg-rose-100 px-3 py-1 text-sm font-bold text-rose-800">Due: {{ number_format($openDue, 2) }} BDT</p>
        @endif
        @if ($resellerHold)
            <p class="mb-3 text-sm rsl-callout rsl-callout--info">Network held — admin turned partner OFF.</p>
        @endif
        <div class="rsl-toolbar">
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <a href="{{ route('reseller.customers.edit', $customer) }}" class="rsl-btn-sm rsl-btn-sm--outline">Edit</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <a href="{{ route('reseller.customers.collect', $customer) }}" class="rsl-btn-sm">Collect payment</a>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE) && ! ($billingPaused ?? false))
                <form method="post" action="{{ route('reseller.customers.invoice.generate', $customer) }}" class="inline">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Generate invoice</button></form>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <form method="post" action="{{ route('reseller.customers.renew', $customer) }}" class="inline">@csrf<input type="hidden" name="days" value="30"><button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Renew 30d</button></form>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_SUSPEND))
                @if ($customer->status === 'active')
                    <form method="post" action="{{ route('reseller.customers.suspend', $customer) }}" class="inline" onsubmit="return confirm('Suspend this subscriber?')">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Suspend</button></form>
                @elseif (in_array($customer->status, ['suspended', 'expired'], true))
                    <form method="post" action="{{ route('reseller.customers.reconnect', $customer) }}" class="inline-flex flex-wrap items-center gap-2">
                        @csrf
                        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::INVOICE_GENERATE))
                            <label class="inline-flex items-center gap-1 text-xs rsl-text">
                                <input type="checkbox" name="generate_bill" value="1" checked class="rounded border-slate-300">
                                This month's Bills
                            </label>
                        @endif
                        <button type="submit" class="rsl-btn-sm">Active + Bills</button>
                    </form>
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
        <div class="rsl-metric rsl-metric--accent"><p class="rsl-metric-label">Sell / mo</p><p class="rsl-metric-value text-base">{{ number_format($pricing['retail_monthly'] ?? 0, 0) }} BDT</p></div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Buy / mo</p>
            <p class="rsl-metric-value text-base">
                @if (($pricing['wholesale_monthly'] ?? null) !== null)
                    {{ number_format($pricing['wholesale_monthly'], 0) }} BDT
                @else
                    —
                @endif
            </p>
        </div>
        <div class="rsl-metric">
            <p class="rsl-metric-label">Margin</p>
            <p class="rsl-metric-value text-base text-emerald-700">
                @if (($pricing['margin_monthly'] ?? null) !== null)
                    {{ number_format($pricing['margin_monthly'], 0) }} BDT
                @else
                    —
                @endif
            </p>
        </div>
        <div class="rsl-metric"><p class="rsl-metric-label">Due</p><p class="rsl-metric-value {{ ($billingPaused ?? false) ? 'text-slate-500' : 'text-rose-700' }}">{{ number_format($openDue, 2) }} BDT</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Status</p><p class="rsl-metric-value text-base capitalize">{{ $customer->status }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">Network</p><p class="rsl-metric-value text-base">{{ $customer->is_ppp_online ? 'Online' : 'Offline' }}</p></div>
        <div class="rsl-metric"><p class="rsl-metric-label">PPPoE</p><p class="rsl-metric-value text-base font-mono text-sm">{{ $customer->mikrotik_secret_name ?: '—' }}</p></div>
    </div>

    @php
        $p = $profile ?? [];
        $tags = $p['tags'] ?? [];
        $activeTags = array_keys(array_filter($tags));
    @endphp
    @if (count($activeTags) > 0)
        <div class="rsl-tag-row mt-4">
            @foreach ($activeTags as $tagKey)
                <span class="rsl-tag-pill rsl-tag-pill--{{ $tagKey }}">{{ str_replace('_', ' ', ucfirst($tagKey)) }}</span>
            @endforeach
        </div>
    @endif

    <div class="rsl-detail-columns mt-6">
        @include('reseller.partials.customer-pricing-panel')
        @include('reseller.partials.customer-account-panel')
    </div>

    @if (($p['payment_plan']['enabled'] ?? false))
        <div class="rsl-panel rsl-panel-pad mt-6 rsl-callout rsl-callout--info">
            <h2 class="rsl-panel-title">Installment plan</h2>
            <p class="mt-2 text-sm">
                <strong>{{ number_format($p['payment_plan']['installment_bdt'] ?? 0, 0) }} BDT</strong> per installment
                @if (! empty($p['payment_plan']['next_due_date']))
                    · next due <strong>{{ $p['payment_plan']['next_due_date'] }}</strong>
                @endif
            </p>
            @if (! empty($p['payment_plan']['note']))
                <p class="mt-1 text-sm rsl-text-muted">{{ $p['payment_plan']['note'] }}</p>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::PAYMENT_COLLECT))
                <a href="{{ route('reseller.customers.collect', $customer) }}" class="rsl-btn-sm mt-3 inline-flex">Record partial payment</a>
            @endif
        </div>
    @endif

    <div class="rsl-detail-columns mt-6">
        <div class="rsl-panel rsl-panel-pad">
            <h2 class="rsl-panel-title">Network binding</h2>
            <dl class="rsl-detail-list rsl-detail-list--compact">
                <div><dt>MAC binding</dt><dd class="font-mono">{{ $p['network']['mac_binding'] ?: '—' }}</dd></div>
                <div><dt>ONU MAC</dt><dd class="font-mono">{{ $p['network']['onu_mac'] ?: '—' }}</dd></div>
                <div><dt>EPON / VLAN</dt><dd>{{ ($p['network']['epon_port'] ?: '—').' / '.($p['network']['vlan'] ?: '—') }}</dd></div>
                <div><dt>Static IP</dt><dd class="font-mono">{{ $p['network']['static_ip'] ?: '—' }}</dd></div>
            </dl>
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <a href="{{ route('reseller.customers.edit', $customer) }}#network-bind" class="rsl-link-action mt-3 inline-block">Edit binding</a>
            @endif
        </div>
        <div class="rsl-panel rsl-panel-pad">
            <h2 class="rsl-panel-title">Field install</h2>
            @if (! empty($p['location']['installation_photo_url']))
                <img src="{{ $p['location']['installation_photo_url'] }}" alt="Installation" class="rsl-install-photo">
            @endif
            @if (! empty($p['location']['gps_lat']) && ! empty($p['location']['gps_lng']))
                <p class="text-sm mt-2">
                    <a href="https://www.google.com/maps?q={{ $p['location']['gps_lat'] }},{{ $p['location']['gps_lng'] }}" target="_blank" rel="noopener" class="rsl-link">
                        Open map ({{ $p['location']['gps_lat'] }}, {{ $p['location']['gps_lng'] }})
                    </a>
                </p>
            @else
                <p class="text-sm rsl-text-muted mt-2">No GPS saved.</p>
            @endif
            @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
                <form method="post" action="{{ route('reseller.customers.installation-photo', $customer) }}" enctype="multipart/form-data" class="mt-4">
                    @csrf
                    <label class="rsl-field-label">Upload installation photo</label>
                    <input type="file" name="installation_photo" accept="image/*" class="rsl-input mt-1" required>
                    <button type="submit" class="rsl-btn-sm mt-2">Upload</button>
                </form>
            @endif
        </div>
    </div>

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT) && ! empty($packageOptions))
        <div class="rsl-panel rsl-panel-pad mt-6" id="package-change">
            <h2 class="rsl-panel-title">Change package</h2>
            @if (! empty($p['pending_package_id']))
                <p class="rsl-callout rsl-callout--info mt-2 text-sm">Pending change on {{ $p['pending_package_effective_date'] ?? 'next cycle' }}.</p>
            @endif
            <form method="post" action="{{ route('reseller.customers.package-change', $customer) }}" class="mt-4 rsl-stack" id="pkg-change-form">
                @csrf
                <div class="rsl-field">
                    <label class="rsl-field-label" for="pkg-select">New package</label>
                    <select id="pkg-select" name="package_id" class="rsl-input" required>
                        @foreach ($packageOptions as $pkg)
                            <option value="{{ $pkg['id'] }}" @selected($pkg['id'] == $customer->package_id)>{{ $pkg['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="pkg-quote-box" class="rsl-callout text-sm hidden"></div>
                <label class="rsl-field-check">
                    <input type="checkbox" name="confirm_upgrade_invoice" value="1" checked>
                    <span>Create upgrade invoice if extra charge due</span>
                </label>
                <button type="submit" class="rsl-btn-sm" onclick="return confirm('Apply package change with shown quote?')">Apply change</button>
            </form>
        </div>
        <script>
            (function () {
                const sel = document.getElementById('pkg-select');
                const box = document.getElementById('pkg-quote-box');
                if (!sel || !box) return;
                const url = @json(route('reseller.customers.package-quote', $customer));
                const load = async () => {
                    const id = sel.value;
                    if (!id) return;
                    box.classList.remove('hidden');
                    box.textContent = 'Loading quote…';
                    try {
                        const r = await fetch(url + '?package_id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } });
                        const q = await r.json();
                        if (!r.ok) throw new Error(q.message || 'Quote failed');
                        box.innerHTML = '<p><strong>' + q.current_package + '</strong> → <strong>' + q.new_package + '</strong></p>'
                            + '<p class="mt-1">Credit: ' + q.credit_amount + ' BDT · New charge: ' + q.new_charge + ' BDT</p>'
                            + '<p class="mt-1 font-bold">Net due now: ' + q.net_due + ' BDT · ' + q.effective_label + '</p>'
                            + (q.estimated_margin_monthly != null ? '<p class="mt-1 text-emerald-700">Est. margin/mo: ' + q.estimated_margin_monthly + ' BDT</p>' : '');
                    } catch (e) {
                        box.textContent = e.message || 'Could not load quote';
                    }
                };
                sel.addEventListener('change', load);
                if (sel.value) load();
            })();
        </script>
    @endif

    <div class="rsl-panel rsl-panel-pad mt-6 overflow-hidden">
        <h2 class="rsl-panel-title">Profit history (12 months)</h2>
        <div class="overflow-x-auto mt-3">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2">Month</th>
                        <th class="px-3 py-2 text-right">Retail</th>
                        <th class="px-3 py-2 text-right">HQ cost</th>
                        <th class="px-3 py-2 text-right">Margin</th>
                        <th class="px-3 py-2 text-right">Bills</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($marginHistory ?? [] as $row)
                        <tr>
                            <td class="px-3 py-2">{{ $row['label'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['retail'] > 0 ? number_format($row['retail'], 0) : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['wholesale'] > 0 ? number_format($row['wholesale'], 0) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ $row['margin'] > 0 ? number_format($row['margin'], 0) : '—' }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['invoices'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rsl-panel rsl-panel-pad mt-6">
        <h2 class="rsl-panel-title">Alerts</h2>
        <p class="text-sm mt-2">
            SMS {{ ($p['notify']['sms'] ?? true) ? 'on' : 'off' }}
            · WhatsApp {{ ($p['notify']['whatsapp'] ?? false) ? 'on' : 'off' }}
            · Email {{ ($p['notify']['email'] ?? false) ? 'on' : 'off' }}
        </p>
        <a href="{{ route('reseller.customers.edit', $customer) }}#tags" class="rsl-link-action mt-2 inline-block">Edit alerts</a>
    </div>

    @if ($portal->canPortal(\App\Support\ResellerPortalPermission::NETWORK_VIEW) && ! empty($networkSession))
        <div class="rsl-panel mt-6 p-6">
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
        <div class="rsl-panel mt-6 p-6 max-w-md">
            <h2 class="rsl-heading mb-3">Change PPPoE password</h2>
            <form method="post" action="{{ route('reseller.customers.password', $customer) }}" class="flex gap-2">
                @csrf
                <input type="password" name="password" required minlength="4" class="rsl-input flex-1" placeholder="New password">
                <button type="submit" class="rsl-btn-sm">Update</button>
            </form>
        </div>
    @endif

    <div class="grid gap-6 mt-6 lg:grid-cols-2">
        <div class="rsl-panel overflow-hidden">
            <div class="rsl-panel-head"><h2 class="rsl-heading">Payment history</h2></div>
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
        <div class="rsl-panel overflow-hidden">
            <div class="rsl-panel-head"><h2 class="rsl-heading">Invoice history</h2></div>
            @if ($billingPaused ?? false)
                @if ($suspensionMonthCurrent ?? false)
                    <p class="px-4 py-3 text-xs text-center rsl-text-muted border-b border-slate-100">Suspend-month bills only (this month).</p>
                @else
                    <p class="px-4 py-6 text-sm text-center rsl-text-muted">new month — while suspended no bill shown। Active + add bill।</p>
                @endif
            @endif
            @if (! ($billingPaused ?? false) || ($suspensionMonthCurrent ?? false))
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
            @endif
        </div>
    </div>
@endsection
