<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="bi bi-router text-primary me-2"></i>{{ __('PPPoE Audit') }}</h3>
            <p class="text-muted mb-0">{{ __('Review PPP, PPPoE and RADIUS authentication events collected from your MikroTik routers.') }}</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="refreshFromRouter" wire:loading.attr="disabled" @disabled(!$selectedRouter)>
            <span wire:loading.remove wire:target="refreshFromRouter"><i class="bi bi-arrow-clockwise me-1"></i>{{ __('Fetch latest router logs') }}</span>
            <span wire:loading wire:target="refreshFromRouter">{{ __('Fetching…') }}</span>
        </button>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-muted text-uppercase fw-semibold">{{ __('Saved PPP-related events') }}</div><div class="fs-3 fw-bold mt-2">{{ number_format($summary['all']) }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-muted text-uppercase fw-semibold">{{ __('Events today') }}</div><div class="fs-3 fw-bold mt-2">{{ number_format($summary['today']) }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm rounded-4"><div class="card-body"><div class="small text-muted text-uppercase fw-semibold">{{ __('Failed or warning events') }}</div><div class="fs-3 fw-bold mt-2 text-danger">{{ number_format($summary['failed']) }}</div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="row g-3 align-items-end">
                <div class="col-lg-3"><label class="form-label small fw-semibold">{{ __('Router') }}</label><select class="form-select" wire:model.live="selectedRouter"><option value="">{{ __('All configured routers') }}</option>@foreach($routers as $router)<option value="{{ $router }}">{{ $router }}</option>@endforeach</select>@error('selectedRouter')<div class="small text-danger">{{ $message }}</div>@enderror</div>
                <div class="col-lg-3"><label class="form-label small fw-semibold">{{ __('Search username or message') }}</label><input type="search" class="form-control" wire:model.live.debounce.350ms="search" placeholder="{{ __('Search logs') }}"></div>
                <div class="col-lg-2"><label class="form-label small fw-semibold">{{ __('Result') }}</label><select class="form-select" wire:model.live="statusFilter"><option value="all">{{ __('All events') }}</option><option value="success">{{ __('Normal events') }}</option><option value="failed">{{ __('Failed / warning') }}</option></select></div>
                <div class="col-lg-2"><label class="form-label small fw-semibold">{{ __('From') }}</label><input type="date" class="form-control" wire:model.live="dateFrom"></div>
                <div class="col-lg-2"><label class="form-label small fw-semibold">{{ __('To') }}</label><input type="date" class="form-control" wire:model.live="dateTo"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 small text-muted">
                <span>{{ __('Router logs must be available in the router’s log buffer or previously stored by the log server.') }}</span>
                <button type="button" class="btn btn-link btn-sm text-decoration-none" wire:click="resetFilters">{{ __('Reset filters') }}</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-4">{{ __('Time recorded') }}</th><th>{{ __('Router') }}</th><th>{{ __('Topics') }}</th><th>{{ __('Event') }}</th><th class="pe-4">{{ __('Result') }}</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $failed = str_contains(strtolower(($log->topics ?? '').' '.($log->message ?? '')), 'error')
                                || str_contains(strtolower(($log->topics ?? '').' '.($log->message ?? '')), 'warning')
                                || str_contains(strtolower($log->message ?? ''), 'fail')
                                || str_contains(strtolower($log->message ?? ''), 'invalid')
                                || str_contains(strtolower($log->message ?? ''), 'reject')
                                || str_contains(strtolower($log->message ?? ''), 'denied');
                        @endphp
                        <tr wire:key="pppoe-audit-{{ $log->id }}">
                            <td class="ps-4 small text-muted">{{ $log->created_at?->format('d M Y, h:i:s A') }}</td>
                            <td class="fw-semibold">{{ $log->router_name }}</td>
                            <td><span class="badge bg-light text-dark">{{ $log->topics ?: '—' }}</span></td>
                            <td class="font-monospace small">{{ $log->message }}</td>
                            <td class="pe-4"><span class="badge bg-{{ $failed ? 'danger' : 'success' }}-subtle text-{{ $failed ? 'danger' : 'success' }}">{{ $failed ? __('Failed / warning') : __('Normal') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-journal-x fs-3 d-block mb-2"></i>{{ __('No matching PPP-related router logs found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="card-footer bg-white border-0">{{ $logs->links() }}</div>@endif
    </div>
</div>
