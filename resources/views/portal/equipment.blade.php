@extends('portal.layout')

@section('title', 'Equipment')

@section('content')
    @php
        $onlineCount = $devices->filter(fn ($device) => in_array(strtolower((string) ($device->onu_oper_status ?? '')), ['up', 'online', 'active'], true))->count();
        $issueCount = $devices->filter(fn ($device) => ! in_array(strtolower((string) ($device->onu_oper_status ?? '')), ['up', 'online', 'active'], true))->count();
    @endphp

    <div class="portal-page-head">
        <div>
            <h1 class="portal-page-title">Network equipment</h1>
            <p class="portal-page-lead">ONU, CPE, and serving OLT details linked with your customer account.</p>
        </div>
        <a href="{{ route('portal.tickets.create') }}" class="portal-card-button">Report device issue</a>
    </div>

    <div class="portal-summary-grid portal-summary-grid--wide">
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Registered devices</p>
            <p class="portal-summary-card__value">{{ $devices->count() }}</p>
            <p class="portal-summary-card__meta">ONU or CPE entries currently mapped to your account.</p>
        </article>
        <article class="portal-summary-card {{ $onlineCount > 0 ? 'portal-summary-card--ok' : 'portal-summary-card--warn' }}">
            <p class="portal-summary-card__eyebrow">Online devices</p>
            <p class="portal-summary-card__value">{{ $onlineCount }}</p>
            <p class="portal-summary-card__meta">Devices reporting up, online, or active status.</p>
        </article>
        <article class="portal-summary-card {{ $issueCount > 0 ? 'portal-summary-card--due' : 'portal-summary-card--ok' }}">
            <p class="portal-summary-card__eyebrow">Need attention</p>
            <p class="portal-summary-card__value">{{ $issueCount }}</p>
            <p class="portal-summary-card__meta">Offline or degraded device records in the current list.</p>
        </article>
        <article class="portal-summary-card portal-summary-card--info">
            <p class="portal-summary-card__eyebrow">Serving OLTs</p>
            <p class="portal-summary-card__value">{{ $olts->count() }}</p>
            <p class="portal-summary-card__meta">OLT nodes currently associated with your devices.</p>
        </article>
    </div>

    @if ($olts->isNotEmpty())
        <section class="portal-surface-card">
            <div class="portal-section-head">
                <div class="portal-label-stack">
                    <h2 class="portal-surface-card__title">Your OLTs</h2>
                    <p class="portal-surface-card__meta">Core node details for the OLTs serving your linked ONU or CPE.</p>
                </div>
            </div>

            <div class="portal-table-wrap">
                <table class="portal-billing-table portal-table-compact">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Serial</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Mgmt IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($olts as $olt)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $olt->adminLabel() }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">{{ $olt->serial_number }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $olt->location ?: '—' }}</td>
                                <td class="px-4 py-3 font-mono text-slate-700">{{ $olt->management_ip ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="portal-surface-card">
        <div class="portal-section-head">
            <div class="portal-label-stack">
                <h2 class="portal-surface-card__title">Your devices</h2>
                <p class="portal-surface-card__meta">Hardware identity, OLT mapping, optical data, and status for customer-side equipment.</p>
            </div>
        </div>

        <div class="portal-table-wrap">
            <table class="portal-billing-table portal-table-compact">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Label</th>
                        <th class="px-4 py-3">Serial</th>
                        <th class="px-4 py-3">OLT</th>
                        <th class="px-4 py-3">PON port</th>
                        <th class="px-4 py-3 text-center">Card</th>
                        <th class="px-4 py-3 text-center">PON</th>
                        <th class="px-4 py-3 text-center">ONU #</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Offline / fault</th>
                        <th class="px-4 py-3 text-right">RX dBm</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($devices as $d)
                        @php
                            $deviceStatus = strtolower((string) ($d->onu_oper_status ?? 'unknown'));
                            $statusClass = match (true) {
                                in_array($deviceStatus, ['up', 'online', 'active'], true) => 'portal-status-pill--success',
                                in_array($deviceStatus, ['degraded', 'warning'], true) => 'portal-status-pill--warning',
                                in_array($deviceStatus, ['down', 'offline', 'los'], true) => 'portal-status-pill--danger',
                                default => 'portal-status-pill--muted',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 capitalize text-slate-800">{{ $d->type }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ $d->display_name ?: '-' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ $d->serial_number }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $d->olt ? $d->olt->adminLabel() : '-' }}</td>
                            <td class="px-4 py-3 font-mono text-slate-700">{{ $d->oltPort ? $d->oltPort->label : '-' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $d->card_no ?? '-' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $d->pon_no ?? '-' }}</td>
                            <td class="px-4 py-3 text-center tabular-nums text-slate-600">{{ $d->onu_index ?? '-' }}</td>
                            <td class="px-4 py-3 capitalize text-slate-800">
                                <span class="portal-status-pill {{ $statusClass }}">{{ str_replace('_', ' ', $d->onu_oper_status ?? 'unknown') }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $d->offline_reason ? \Illuminate\Support\Str::limit($d->offline_reason, 80) : '-' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-600">{{ $d->rx_power_dbm !== null ? number_format((float) $d->rx_power_dbm, 2) : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-slate-500">No equipment registered yet. Contact support if this is unexpected.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($devices->isEmpty())
            <div class="portal-form-actions">
                <a href="{{ route('portal.tickets.create') }}" class="portal-btn-primary portal-btn-ticket">Ask support to verify device mapping</a>
            </div>
        @endif
    </section>
@endsection
