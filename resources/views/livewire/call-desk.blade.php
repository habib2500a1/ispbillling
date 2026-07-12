<div>
    <x-slot name="header">
        {{ __('Call Desk') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Agent workspace') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updatedAt)->diffForHumans() }}
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <a href="{{ route('admin-tickets') }}" class="btn btn-sm btn-outline-secondary">{{ __('Tickets') }}</a>
            <a href="{{ route('billing-notices') }}" class="btn btn-sm btn-outline-secondary">{{ __('Billing Notices') }}</a>
            <a href="{{ route('noc-overview') }}" class="btn btn-sm btn-primary">{{ __('NOC') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#0f172a,#1e3a5f);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Calls today') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['calls_today'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('No answer') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['no_answer_today'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#4f46e5,#4338ca);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Callbacks') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['callbacks'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Open tickets') }}</div>
                    <div class="fs-3 fw-bold">{{ $stats['open_tickets'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('SLA breached') }}</div>
                    <div class="fs-3 fw-bold text-danger">{{ $stats['sla_breached'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Due to call') }}</div>
                    <div class="fs-3 fw-bold text-warning">{{ $stats['due_to_call'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-body">
                    <label class="form-label fw-semibold">{{ __('Find subscriber') }}</label>
                    <input type="search" class="form-control" placeholder="{{ __('Name, mobile, UID, PPP…') }}"
                        wire:model.live.debounce.300ms="search">
                    @if(count($searchResults))
                        <div class="list-group list-group-flush mt-2 border rounded">
                            @foreach($searchResults as $row)
                                <button type="button" class="list-group-item list-group-item-action"
                                    wire:click="selectCustomer('{{ $row['customer_unique_id'] }}')">
                                    <div class="fw-semibold">{{ $row['customer_name'] }}</div>
                                    <div class="small text-muted">
                                        {{ $row['mobile'] ?: '—' }} · {{ $row['customer_unique_id'] }}
                                        @if($row['ppp_username']) · PPP {{ $row['ppp_username'] }} @endif
                                        · {{ __('Due') }} {{ number_format($row['due_amount'], 2) }}
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($context)
                @php
                    $c = $context['customer'];
                @endphp
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-transparent border-0 pt-3">
                        <h6 class="fw-bold mb-0">{{ $c['customer_name'] }}</h6>
                        <div class="small text-muted">{{ $c['customer_unique_id'] }} · {{ $c['status'] }}</div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-2 small mb-3">
                            <div class="col-6"><span class="text-muted">{{ __('Mobile') }}</span><div class="fw-semibold">{{ $c['mobile'] ?: '—' }}</div></div>
                            <div class="col-6"><span class="text-muted">{{ __('Alt') }}</span><div class="fw-semibold">{{ $c['alternative_mobile'] ?: '—' }}</div></div>
                            <div class="col-6"><span class="text-muted">{{ __('PPP') }}</span><div class="fw-semibold">{{ $c['ppp_username'] ?: '—' }}</div></div>
                            <div class="col-6"><span class="text-muted">{{ __('Due') }}</span><div class="fw-semibold text-danger">{{ number_format($c['due_amount'], 2) }}</div></div>
                            <div class="col-6"><span class="text-muted">{{ __('Rent') }}</span><div>{{ number_format($c['monthly_rent'], 2) }}</div></div>
                            <div class="col-6"><span class="text-muted">{{ __('Disable date') }}</span><div>{{ $c['auto_disable_date'] ?: '—' }}</div></div>
                        </div>

                        <div class="border-top pt-3">
                            <h6 class="fw-semibold">{{ __('Log call') }}</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ __('Phone') }}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model="phone">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ __('Direction') }}</label>
                                    <select class="form-select form-select-sm" wire:model="direction">
                                        <option value="outbound">{{ __('Outbound') }}</option>
                                        <option value="inbound">{{ __('Inbound') }}</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ __('Outcome') }}</label>
                                    <select class="form-select form-select-sm" wire:model="outcome">
                                        @foreach($outcomes as $key => $label)
                                            <option value="{{ $key }}">{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ __('Duration (sec)') }}</label>
                                    <input type="number" min="0" class="form-control form-control-sm" wire:model="duration">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-0">{{ __('Remarks') }}</label>
                                    <textarea class="form-control form-control-sm" rows="2" wire:model="remarks"></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="createTicket" wire:model="createTicket">
                                        <label class="form-check-label small" for="createTicket">{{ __('Also open support ticket') }}</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="logCall" wire:loading.attr="disabled">
                                        <i class="bi bi-telephone-outbound"></i> {{ __('Save call log') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if(count($context['tickets'] ?? []))
                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-semibold">{{ __('Recent tickets') }}</h6>
                                @foreach($context['tickets'] as $t)
                                    <div class="d-flex justify-content-between small py-1 border-bottom">
                                        <div>
                                            <span class="fw-semibold">{{ $t['ticket_no'] }}</span>
                                            <span class="text-muted">{{ $t['subject'] }}</span>
                                        </div>
                                        <span class="badge bg-{{ $t['sla_badge'] }}">{{ $t['status'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-7">
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach([
                    'call_queue' => __('Due / call queue'),
                    'tickets' => __('Open tickets'),
                    'callbacks' => __('Callbacks'),
                    'recent' => __('Recent calls'),
                ] as $key => $label)
                    <button type="button"
                        class="btn btn-sm {{ $activeQueue === $key ? 'btn-dark' : 'btn-outline-dark' }}"
                        wire:click="setQueue('{{ $key }}')">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body">
                    @if($activeQueue === 'call_queue')
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Customer') }}</th>
                                        <th>{{ __('Mobile') }}</th>
                                        <th class="text-end">{{ __('Due') }}</th>
                                        <th>{{ __('Disable') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($callQueue as $row)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold text-truncate" style="max-width:160px;">{{ $row['customer_name'] }}</div>
                                                <div class="small text-muted">{{ $row['customer_unique_id'] }} · {{ $row['status'] }}</div>
                                            </td>
                                            <td>{{ $row['mobile'] ?: '—' }}</td>
                                            <td class="text-end fw-semibold">{{ number_format($row['due_amount'], 2) }}</td>
                                            <td>{{ $row['auto_disable_date'] ?: '—' }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    wire:click="selectFromQueue('{{ $row['customer_unique_id'] }}')">{{ __('Open') }}</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-muted small">{{ __('No due customers in queue.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif($activeQueue === 'tickets')
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Ticket') }}</th>
                                        <th>{{ __('Priority') }}</th>
                                        <th>{{ __('SLA') }}</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($openTickets as $t)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $t['ticket_no'] }}</div>
                                                <div class="small text-muted text-truncate" style="max-width:220px;">{{ $t['subject'] }}</div>
                                            </td>
                                            <td><span class="badge bg-{{ $t['priority'] === 'high' ? 'danger' : ($t['priority'] === 'medium' ? 'warning' : 'secondary') }}">{{ $t['priority'] }}</span></td>
                                            <td><span class="badge bg-{{ $t['sla_badge'] }}">{{ $t['sla'] }}</span></td>
                                            <td>
                                                @if($t['customer_unique_id'])
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        wire:click="selectFromQueue('{{ $t['customer_unique_id'] }}')">{{ __('Open') }}</button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-muted small">{{ __('No open tickets.') }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif($activeQueue === 'callbacks')
                        @include('livewire.partials.call-desk-calls', ['rows' => $callbacks])
                    @else
                        @include('livewire.partials.call-desk-calls', ['rows' => $recentCalls])
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
