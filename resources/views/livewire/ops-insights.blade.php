<div>
    <x-slot name="header">
        {{ __('Ops Insights') }}
    </x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
            {{ __('Rule-based AI digest') }} · {{ __('Updated') }}: {{ \Carbon\Carbon::parse($updatedAt)->diffForHumans() }}
            <span class="badge bg-light text-dark border ms-1">{{ __('No LLM') }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="refresh">
                <i class="bi bi-arrow-repeat"></i> {{ __('Refresh') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-dark" wire:click="publishDigest">
                <i class="bi bi-journal-check"></i> {{ __('Save digest') }}
            </button>
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary">{{ __('Dashboard') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#991b1b,#7f1d1d);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Critical') }}</div>
                    <div class="fs-3 fw-bold">{{ $counts['critical'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#b45309,#92400e);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('High') }}</div>
                    <div class="fs-3 fw-bold">{{ $counts['high'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 text-white" style="border-radius:12px;background:linear-gradient(135deg,#1e3a5f,#0f172a);">
                <div class="card-body py-3">
                    <div class="small text-uppercase opacity-75">{{ __('Medium / Low') }}</div>
                    <div class="fs-3 fw-bold">{{ $counts['medium'] + $counts['low'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase">{{ __('Insights') }}</div>
                    <div class="fs-3 fw-bold">{{ $summary['insight_total'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach([
            'all' => __('All'),
            'critical' => __('Critical'),
            'high' => __('High'),
            'medium' => __('Medium'),
            'low' => __('Low'),
        ] as $key => $label)
            <button type="button" class="btn btn-sm {{ $severityFilter === $key ? 'btn-dark' : 'btn-outline-dark' }}"
                wire:click="setSeverity('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Action queue') }}</h6>
                </div>
                <div class="card-body pt-0">
                    @forelse($insights as $row)
                        <div class="border rounded-3 p-3 mb-2">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <span class="badge bg-{{ $row['severity'] === 'critical' ? 'danger' : ($row['severity'] === 'high' ? 'warning text-dark' : ($row['severity'] === 'medium' ? 'info' : 'secondary')) }}">
                                        {{ strtoupper($row['severity']) }}
                                    </span>
                                    <span class="badge bg-light text-dark border">{{ $row['domain'] }}</span>
                                    <div class="fw-bold mt-1">{{ $row['title'] }}</div>
                                    <div class="small text-muted">{{ $row['message'] }}</div>
                                </div>
                                @if($row['url'])
                                    <a href="{{ $row['url'] }}" class="btn btn-sm btn-outline-primary align-self-start">{{ $row['link_label'] }}</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small py-4 text-center">{{ __('No insights for this filter — looking good.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Snapshot') }}</h6>
                </div>
                <div class="card-body pt-0 small">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ __('Today collection') }}</span>
                        <strong>{{ number_format($summary['today_collection'], 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ __('Overdue') }}</span>
                        <strong>{{ $summary['overdue'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ __('Due soon') }}</span>
                        <strong>{{ $summary['due_soon'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ __('Open tickets') }}</span>
                        <strong>{{ $summary['open_tickets'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ __('SLA breached') }}</span>
                        <strong class="text-danger">{{ $summary['sla_breached'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>{{ __('Low stock') }}</span>
                        <strong>{{ $summary['low_stock'] }}</strong>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <h6 class="fw-bold mb-0">{{ __('Text digest') }}</h6>
                </div>
                <div class="card-body pt-0">
                    <pre class="small bg-light border rounded-3 p-3 mb-0" style="white-space:pre-wrap;">{{ $digest }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>
