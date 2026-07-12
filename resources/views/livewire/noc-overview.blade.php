<div>
    <x-slot name="header">
        {{ __('NOC Overview') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updated_at)->diffForHumans() }}
            @if($optical['bridge'] ?? false)
                <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">{{ __('Optical live') }}</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('admin-tickets') }}" class="btn btn-sm btn-outline-secondary">{{ __('Tickets') }}</a>
            <a href="{{ route('onu-management') }}" class="btn btn-sm btn-primary">{{ __('Optical / ONU') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('OLT / ONU') }}</div>
                    <div class="fs-3 fw-bold">{{ $optical['olts'] ?? 0 }} <span class="fs-6 fw-normal opacity-75">OLT</span></div>
                    <div>{{ number_format($optical['onus'] ?? 0) }} ONU · {{ __('Linked') }} {{ $optical['linked'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#059669,#047857);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('RX OK') }}</div>
                    <div class="fs-3 fw-bold">{{ $optical['rx_ok'] ?? 0 }}</div>
                    <div>{{ __('Avg') }}: {{ isset($optical['avg_rx']) && $optical['avg_rx'] !== null ? number_format($optical['avg_rx'], 2).' dBm' : '—' }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#d97706,#b45309);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Weak / Critical RX') }}</div>
                    <div class="fs-3 fw-bold">{{ ($optical['rx_weak'] ?? 0) + ($optical['rx_critical'] ?? 0) }}</div>
                    <div>{{ __('Weak') }} {{ $optical['rx_weak'] ?? 0 }} · {{ __('Critical') }} {{ $optical['rx_critical'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">{{ __('Routers') }}</div>
                    <div class="fs-3 fw-bold">{{ $network['routers_connected'] ?? 0 }} <span class="fs-6 fw-normal opacity-75">/ {{ $network['routers'] ?? 0 }}</span></div>
                    <div>{{ __('Local OLT') }} {{ $network['olts_local'] ?? 0 }} · ONU {{ $network['onus_local'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('Tickets open') }}</div>
                    <div class="fs-3 fw-bold">{{ $tickets['open'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('In progress') }}</div>
                    <div class="fs-3 fw-bold text-primary">{{ $tickets['in_progress'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('SLA breached') }}</div>
                    <div class="fs-3 fw-bold text-danger">{{ $tickets['breached'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">{{ __('High priority open') }}</div>
                    <div class="fs-3 fw-bold text-warning">{{ $tickets['high_open'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('SLA breached tickets') }}</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Ticket') }}</th>
                                    <th>{{ __('Priority') }}</th>
                                    <th>{{ __('SLA') }}</th>
                                    <th>{{ __('Due') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($breached_tickets as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $row['ticket_no'] }}</div>
                                            <div class="small text-muted text-truncate" style="max-width:180px;">{{ $row['subject'] }}</div>
                                        </td>
                                        <td><span class="badge bg-{{ $row['priority'] === 'high' ? 'danger' : ($row['priority'] === 'medium' ? 'warning' : 'secondary') }}">{{ $row['priority'] }}</span></td>
                                        <td><span class="badge bg-danger">{{ str_replace('_', ' ', $row['sla']) }}</span></td>
                                        <td class="small">{{ $row['resolve_due'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No SLA breaches.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between">
                    <h6 class="fw-bold mb-0">{{ __('Recent open tickets') }}</h6>
                    <a href="{{ route('admin-tickets') }}" class="small">{{ __('View all') }} →</a>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Ticket') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('SLA') }}</th>
                                    <th>{{ __('Age') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recent_open as $row)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $row['ticket_no'] }}</div>
                                            <div class="small text-muted text-truncate" style="max-width:180px;">{{ $row['subject'] }}</div>
                                        </td>
                                        <td>{{ $row['status'] }}</td>
                                        <td>
                                            @php $ok = ($row['sla'] ?? '') === 'within_sla'; @endphp
                                            <span class="badge bg-{{ $ok ? 'success' : 'danger' }}">{{ str_replace('_', ' ', $row['sla']) }}</span>
                                        </td>
                                        <td class="small">{{ $row['age'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No open tickets.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
