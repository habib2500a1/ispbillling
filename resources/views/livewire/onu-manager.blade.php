<div>
    <x-slot name="header">
        {{ __('Optical / ONU') }}
    </x-slot>

    <style>
        .os-shell { --os-ink:#1e3a5f; --os-muted:#64748b; --os-line:#e8eef5; --os-soft:#f4f7fb; }
        .os-shell .os-hero { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:1.1rem 1.2rem; margin-bottom:1rem; }
        .os-shell .os-kicker { font-size:.68rem; letter-spacing:.12em; text-transform:uppercase; font-weight:800; color:#64748b; }
        .os-shell .os-title { margin:.15rem 0 0; font-size:1.25rem; font-weight:800; color:var(--os-ink); }
        .os-shell .os-sub { margin:.3rem 0 0; color:var(--os-muted); font-size:.86rem; }
        .os-shell .os-kpi { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:.9rem 1rem; height:100%; }
        .os-shell .os-kpi .label { font-size:.68rem; letter-spacing:.07em; text-transform:uppercase; color:var(--os-muted); font-weight:700; }
        .os-shell .os-kpi .value { font-size:1.4rem; font-weight:800; color:var(--os-ink); }
        .os-shell .os-panel { background:#fff; border:1px solid var(--os-line); border-radius:14px; overflow:hidden; }
        .os-shell .os-toolbar { background:#fff; border:1px solid var(--os-line); border-radius:14px; padding:.9rem 1rem; margin-bottom:1rem; }
        .os-shell table { font-size:.86rem; }
        .os-shell thead th { font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; color:var(--os-muted); }
    </style>

    <div class="os-shell">
        <div class="os-hero">
            <div class="os-kicker">{{ __('Network') }}</div>
            <h1 class="os-title">{{ __('Optical / ONU') }}</h1>
            <p class="os-sub">{{ __('ONU list comes from this panel — link a subscriber to an OLT / PON / MAC. A second ispbilling server is not used.') }}</p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="os-kpi">
                    <div class="label">{{ __('Linked ONUs') }}</div>
                    <div class="value">{{ number_format($onus->total()) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="os-kpi">
                    <div class="label">{{ __('OLTs') }}</div>
                    <div class="value">{{ number_format($oltCount) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="os-kpi">
                    <div class="label">{{ __('Subscribers') }}</div>
                    <div class="value">{{ number_format($customerCount) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="os-kpi">
                    <div class="label">{{ __('How to fill') }}</div>
                    <div class="small text-muted mt-1">{{ __('Add ONU below, or save optical on the customer page.') }}</div>
                </div>
            </div>
        </div>

        @if($statusMessage)
            <div class="alert {{ $statusOk ? 'alert-success' : 'alert-warning' }}">{{ $statusMessage }}</div>
        @endif

        <div class="os-toolbar d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="search" class="form-control form-control-sm" style="max-width: 260px;" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search ONU / customer...') }}">
                @if($bridgeEnabled)
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm {{ $tab === 'local' ? 'btn-dark' : 'btn-outline-secondary' }}" wire:click="setTab('local')">{{ __('Linked') }}</button>
                        <button type="button" class="btn btn-sm {{ $tab === 'remote' ? 'btn-dark' : 'btn-outline-secondary' }}" wire:click="setTab('remote')">{{ __('Remote feed') }}</button>
                    </div>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('olt-management') }}" class="btn btn-sm btn-outline-secondary">{{ __('OLT list') }}</a>
                <button type="button" class="btn btn-sm btn-dark" wire:click="startCreate">{{ __('Add ONU') }}</button>
            </div>
        </div>

        @if($showForm)
            <div class="os-panel mb-3 p-3">
                <div class="fw-bold mb-2" style="color:#1e3a5f;">{{ __('Link ONU to subscriber') }}</div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">{{ __('Subscriber') }}</label>
                        <select class="form-select form-select-sm" wire:model="customer_id">
                            <option value="">{{ __('Select customer') }}</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->customer_name }} · {{ $c->customer_unique_id }}</option>
                            @endforeach
                        </select>
                        @error('customer_id') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">{{ __('OLT') }}</label>
                        <select class="form-select form-select-sm" wire:model="olt_id">
                            <option value="">{{ __('Optional') }}</option>
                            @foreach($olts as $olt)
                                <option value="{{ $olt->id }}">{{ $olt->name }}@if($olt->management_ip) · {{ $olt->management_ip }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">PON</label>
                        <input type="text" class="form-control form-control-sm" wire:model="pon_port" placeholder="0/1/1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">MAC</label>
                        <input type="text" class="form-control form-control-sm" wire:model="mac_address" placeholder="AA:BB:…">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">{{ __('Serial') }}</label>
                        <input type="text" class="form-control form-control-sm" wire:model="serial_number">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">RX dBm</label>
                        <input type="text" class="form-control form-control-sm" wire:model="rx_power_dbm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">TX dBm</label>
                        <input type="text" class="form-control form-control-sm" wire:model="tx_power_dbm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">{{ __('Status') }}</label>
                        <select class="form-select form-select-sm" wire:model="oper_status">
                            <option value="online">online</option>
                            <option value="offline">offline</option>
                            <option value="los">los</option>
                            <option value="unknown">unknown</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-dark" wire:click="saveOnu">{{ __('Save ONU') }}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelForm">{{ __('Cancel') }}</button>
                </div>
            </div>
        @endif

        @if($tab === 'local')
            <div class="os-panel">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Customer') }}</th>
                                <th>PPP</th>
                                <th>RX / TX</th>
                                <th>OLT</th>
                                <th>PON</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Updated') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($onus as $onu)
                                <tr wire:key="onu-{{ $onu->id }}">
                                    <td>
                                        @if($onu->customer)
                                            <a href="{{ route('customers.show', encrypt($onu->customer->customer_unique_id)) }}" class="fw-semibold text-decoration-none">
                                                {{ $onu->customer->customer_name }}
                                            </a>
                                            <div class="small text-muted">{{ $onu->customer->customer_unique_id }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="font-monospace small">{{ $onu->customer?->pppUser?->username ?: '—' }}</td>
                                    <td class="font-monospace">
                                        {{ $onu->rx_power_dbm !== null ? number_format((float)$onu->rx_power_dbm, 2).' dBm' : '—' }}
                                        <span class="text-muted">/</span>
                                        {{ $onu->tx_power_dbm !== null ? number_format((float)$onu->tx_power_dbm, 2).' dBm' : '—' }}
                                    </td>
                                    <td>{{ $onu->olt_name ?: ($onu->olt?->name ?: '—') }}</td>
                                    <td class="font-monospace small">{{ $onu->pon_port ?: '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $onu->oper_status ?: $onu->source }}</span></td>
                                    <td class="small">{{ optional($onu->last_polled_at)->diffForHumans() ?? '—' }}</td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="refreshCustomer({{ $onu->id }})">{{ __('Refresh') }}</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteLocal({{ $onu->id }})" wire:confirm="{{ __('Delete this ONU row?') }}">{{ __('Delete') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        {{ __('No ONU linked yet.') }}
                                        @if($customerCount < 1)
                                            {{ __('Add a subscriber first.') }}
                                        @elseif($oltCount < 1)
                                            {{ __('Add an OLT, then click Add ONU.') }}
                                        @else
                                            {{ __('Click Add ONU and attach MAC / PON to the subscriber.') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($onus->hasPages())
                    <div class="card-footer">{{ $onus->links() }}</div>
                @endif
            </div>
        @elseif($bridgeEnabled)
            <div class="os-panel">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Customer') }}</th>
                                <th>PPP</th>
                                <th>RX / TX</th>
                                <th>OLT</th>
                                <th>PON</th>
                                <th>MAC</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($remoteOnus as $row)
                                <tr wire:key="remote-{{ $row->onu_id }}">
                                    <td class="fw-semibold">{{ $row->customer_name ?: '—' }}</td>
                                    <td class="font-monospace small">{{ $row->radius_username ?: '—' }}</td>
                                    <td class="font-monospace">
                                        {{ $row->rx_power_dbm !== null ? number_format((float)$row->rx_power_dbm, 2).' dBm' : '—' }}
                                        /
                                        {{ $row->tx_power_dbm !== null ? number_format((float)$row->tx_power_dbm, 2).' dBm' : '—' }}
                                    </td>
                                    <td>{{ $row->olt_name ?: '—' }}</td>
                                    <td class="font-monospace small">{{ $row->display_name ?: '—' }}</td>
                                    <td class="font-monospace small">{{ $row->mac_address ?: '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $row->onu_oper_status ?: '—' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No remote ONU rows.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
